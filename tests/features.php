<?php
declare(strict_types=1);

// Included at the end of tests/run.php (shares its helpers: check(), $tmp, $mkG …). Covers: four languages, conditional "Chosen design",
// Careers (public + admin), Team, customer accounts/CRM and isolation, Dreamers placeholder, projects, uploads, ZIP handling and the isolated preview origin.

use Pixelite\{Careers, Csrf, CustomerAuth, Customers, Database, Env, I18n, Preview, ProjectStorage, Projects, Request, TemplateCatalog, ZipReader};

echo "\nfeatures: setup\n";
$PR = "$tmp/pr-" . bin2hex(random_bytes(3));   // a fresh projects folder per run (this file runs twice: legacy and new templates)
Env::set('PROJECTS_DIR', $PR);
Env::set('CONTACT_EMAIL', 'hello@pixelite.test');
Env::set('PREVIEW_ORIGIN', '');
$rq = fn(string $m, string $p, array $post = [], array $q = [], string $ip = '203.0.113.200', string $host = 'pixelite.cz', array $files = []) => new Request($m, $p, $post, $q, ['REMOTE_ADDR' => $ip, 'HTTP_HOST' => $host], $files);
$H = fn(Request $r) => Pixelite\handle($r);
$guest = function (): void { $_SESSION = []; };
$admin = function (): void { $_SESSION = ['admin_at' => time(), 'admin_since' => time(), '_csrf' => 'adm-token']; };
$cust = function (int $id): void { $_SESSION = ['customer_id' => $id, 'customer_at' => time(), 'customer_since' => time(), '_csrf' => 'cust-token']; };
$loc = fn(Pixelite\Response $r): string => (string) ($r->headers['Location'] ?? '');
$cnt = fn(string $sql, array $a = []): int => (function () use ($sql, $a) { $s = Database::pdo()->prepare($sql); $s->execute($a); return (int) $s->fetchColumn(); })();
$guest();

/* ---------------------------------------------------------------- four languages */
echo "four languages\n";
check('the platform supports en, cs, ru and uk', I18n::LOCALES === ['en', 'cs', 'ru', 'uk']);
$cyr = ['ru' => '/[ыэъё]/u', 'uk' => '/[іїєґ]/u'];
$pagesAll = ['', 'pricing', 'process', 'contact', 'privacy', 'cookies', 'terms', 'order', 'templates', 'templates/lumora', 'team', 'careers', 'login', 'register', 'portfolio'];
foreach (I18n::LOCALES as $lg) {
    $ok = true; $why = '';
    foreach ($pagesAll as $pg) {
        $guest();
        $r = $H($rq('GET', "/$lg/$pg"));
        if ($r->status !== 200 || !str_contains($r->body, "<html lang=\"$lg\">") || !str_contains($r->body, 'rel="canonical" href="https://pixelite.cz/' . $lg . '/' . $pg . '"') && $pg !== '') { $ok = false; $why .= " $pg:{$r->status}"; }
        if (substr_count($r->body, '<link rel="alternate" hreflang="') !== count(I18n::LOCALES) + 1 || preg_match_all('#<a [^>]*hreflang="#', $r->body) < count(I18n::LOCALES)) { /* head alternates (n+1) + at least one language switcher with n links */ $ok = false; $why .= " hreflang:$pg"; }
        if (preg_match('/\b(?:careers|team|auth|account|nav|footer|templates|order)\.[a-z_]{3,}\b/', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $r->body)))) { $ok = false; $why .= " rawkey:$pg"; }
    }
    check("$lg: every public page renders, correct <html lang>, canonical, 4 language links + hreflang, no untranslated keys", $ok, $why);
}
foreach ($cyr as $lg => $re) {
    check("$lg: pages really are in that language (not English fallback)", (bool) preg_match($re, $page("/$lg/")) && (bool) preg_match($re, $page("/$lg/privacy")) && (bool) preg_match($re, $page("/$lg/order")) && (bool) preg_match($re, $page("/$lg/careers")));
}
check('language files contain no control characters and prices keep a non-breaking space (2\u{00A0}999)', (function () {
    foreach (I18n::LOCALES as $l) { if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', (string) file_get_contents(Pixelite\Paths::root("lang/$l.php")))) return false; }
    foreach (['cs', 'ru', 'uk'] as $l) { if (I18n::get('services.items', [], $l)[0]['amount'] !== "2\u{00A0}999") return false; }
    return I18n::get('services.items', [], 'en')[0]['amount'] === '2,999';
})());
check('language switcher offers all four languages and keeps the page', (function () use ($page) {
    $b = $page('/ru/pricing');
    foreach (I18n::LOCALES as $l) { if (!str_contains($b, 'href="/' . $l . '/pricing" hreflang="' . $l . '"')) return false; }
    return true;
})());
check('Accept-Language picks ru/uk for the bare address', (function () use ($H, $rq) {
    foreach (['ru' => 'ru-RU,ru;q=0.9,en;q=0.5', 'uk' => 'uk-UA,uk;q=0.9', 'cs' => 'cs,en;q=0.5', 'en' => 'de-DE,de;q=0.9,en;q=0.2'] as $want => $hdr) {
        $r = $H(new Request('GET', '/', [], [], ['REMOTE_ADDR' => '203.0.113.201', 'HTTP_ACCEPT_LANGUAGE' => $hdr]));
        if (($r->headers['Location'] ?? '') !== "/$want/") return false;
    }
    return true;
})());
check('sitemap lists every language for team and careers, not account/login/register', (function () use ($page) {
    $sm = $page('/sitemap.xml');
    foreach (I18n::LOCALES as $l) { if (!str_contains($sm, "https://pixelite.cz/$l/team</loc>") || !str_contains($sm, "https://pixelite.cz/$l/careers</loc>")) return false; }
    return !str_contains($sm, '/login') && !str_contains($sm, '/register') && !str_contains($sm, '/account');
})());
check('unknown language prefixes stay 404 (de, fr, EN)', array_reduce(['/de/', '/fr/pricing', '/EN/'], fn($c, $p) => $c && $H($rq('GET', $p))->status === 404, true));
check('existing English and Czech URLs are unchanged', $H($rq('GET', '/en/pricing'))->status === 200 && $H($rq('GET', '/cs/pricing'))->status === 200 && $H($rq('GET', '/ru/pricing'))->status === 200);

/* ---------------------------------------------------------------- conditional "Chosen design" */
echo "chosen design (order/contact flow)\n";
foreach (I18n::LOCALES as $lg) {
    $plain = $page("/$lg/order");
    check("order ($lg): the ordinary form has NO Chosen design field", !str_contains($plain, 'data-template-pick') && !str_contains($plain, 'name="template"') && !str_contains($plain, 'id="f-template"'));
    $with = $H($rq('GET', "/$lg/order", [], ['template' => 'lumora']))->body;
    $title = TemplateCatalog::localized(TemplateCatalog::find('lumora'), $lg)['title'];
    check("order ($lg): with a valid design the field appears, preselected, name from the catalog", str_contains($with, 'data-template-pick') && (bool) preg_match('#<option value="lumora"[^>]*selected>' . preg_quote($title, '#') . '</option>#', $with));
}
check('order: invalid / hostile ?template= values never show the field', array_reduce(['nope', 'LUMORA', '../etc', 'lumora"><script>', str_repeat('a', 300), "lumora\0"], fn($c, $t) => $c && !str_contains($H($rq('GET', '/en/order', [], ['template' => $t]))->body, 'data-template-pick'), true));
$_SESSION = []; $H($rq('GET', '/en/order')); $_SESSION['form_ts'] = time() - 30; $_SESSION['_csrf'] = 'tok';
$bad = $H($rq('POST', '/en/order', ['_csrf' => 'tok', 'template' => 'not-a-design', 'name' => 'Ann', 'email' => 'ann@example.cz', 'project_type' => 'website', 'budget' => 'unsure', 'timeframe' => 'flexible', 'description' => 'A tampered design value must not be accepted.', 'consent' => '1'], [], '203.0.113.210'));
check('order: a tampered design value => 422 and the field is shown with an error so it can be corrected', $bad->status === 422 && str_contains($bad->body, 'data-template-pick') && str_contains($bad->body, 'id="e-template"'));
$_SESSION['form_ts'] = time() - 30;
$bad2 = $H($rq('POST', '/en/order', ['_csrf' => 'tok', 'template' => 'lumora', 'name' => 'Ann', 'email' => 'not-an-email', 'project_type' => 'website', 'budget' => 'unsure', 'timeframe' => 'flexible', 'description' => 'Valid design, invalid email.', 'consent' => '1'], [], '203.0.113.210'));
check('order: a valid design survives a validation error (still shown and selected)', $bad2->status === 422 && (bool) preg_match('#<option value="lumora"[^>]*selected>#', $bad2->body));
$_SESSION['form_ts'] = time() - 30;
$noTpl = $H($rq('POST', '/en/order', ['_csrf' => 'tok', 'name' => 'Ann', 'email' => 'not-an-email', 'project_type' => 'website', 'budget' => 'unsure', 'timeframe' => 'flexible', 'description' => 'No design chosen, invalid email.', 'consent' => '1'], [], '203.0.113.210'));
check('order: without a design the field stays absent after a validation error', $noTpl->status === 422 && !str_contains($noTpl->body, 'data-template-pick'));
$_SESSION['form_ts'] = time() - 30;
$okTpl = $H($rq('POST', '/en/order', ['_csrf' => 'tok', 'template' => 'lumora', 'template_name' => 'HACKED', 'name' => 'Ann Design', 'email' => 'ann-design@example.cz', 'project_type' => 'website', 'budget' => 'unsure', 'timeframe' => 'flexible', 'description' => 'Valid order with a design.', 'consent' => '1'], [], '203.0.113.211'));
$ordRow = Database::pdo()->query("SELECT * FROM orders WHERE email = 'ann-design@example.cz'")->fetch();
check('order: the stored template name is derived from the catalog (client name ignored)', $okTpl->status === 303 && $ordRow && $ordRow['template'] === 'lumora' && $ordRow['template_name'] === TemplateCatalog::englishTitle('lumora'));
foreach (I18n::LOCALES as $lg) {
    check("contact ($lg): no Chosen design by default; shown (name from the catalog) only for a valid design; CTA keeps the design", (function () use ($H, $rq, $lg) {
        $d = $H($rq('GET', "/$lg/contact"))->body; $v = $H($rq('GET', "/$lg/contact", [], ['template' => 'lumora']))->body; $x = $H($rq('GET', "/$lg/contact", [], ['template' => '"><img src=x onerror=alert(1)>']))->body;
        $label = I18n::get('contact.chosen_design', [], $lg);
        return !str_contains($d, 'contact-design') && str_contains($v, 'contact-design') && str_contains($v, $label . ':</strong> Lumora') && str_contains($v, 'href="/' . $lg . '/order?template=lumora"')
            && !str_contains($x, 'contact-design') && !str_contains($x, '<img src=x') && !str_contains($d, '?template=');
    })());
}
check('design page links to the contact form with that design', str_contains($page('/en/templates/lumora'), 'href="/en/contact?template=lumora"'));

/* ---------------------------------------------------------------- Careers */
echo "careers\n";
foreach (I18n::LOCALES as $lg) {
    $b = $page("/$lg/careers");
    check("careers ($lg): no vacancies => not-hiring message + working mailto with the configured address, no invented address", str_contains($b, I18n::get('careers.none_title', [], $lg)) && str_contains($b, 'href="mailto:hello@pixelite.test"') && !str_contains($b, 'class="vacancy"') && substr_count($b, 'mailto:') >= 2 && !preg_match('/mailto:(?!hello@pixelite\.test)/', $b));
}
Env::set('CONTACT_EMAIL', '');
check('careers: with no configured email the page shows the visible placeholder instead of an invented address', str_contains($page('/en/careers'), 'class="placeholder"') && !str_contains($page('/en/careers'), 'mailto:'));
Env::set('CONTACT_EMAIL', 'hello@pixelite.test');
// admin: authorisation, CSRF, validation
foreach (['/admin/careers', '/admin/careers/new', '/admin/careers/1', '/admin/careers/1/delete'] as $u) {
    $guest(); $a = $H($rq('GET', $u)); $cust(1); $b = $H($rq('GET', $u));
    check("careers admin GET $u: guests and signed-in customers are sent to the staff login", $a->status === 302 && $loc($a) === '/admin/login' && $b->status === 302 && $loc($b) === '/admin/login' && $a->body === '' && $b->body === '');
}
foreach (['/admin/careers', '/admin/careers/1', '/admin/careers/1/publish', '/admin/careers/1/unpublish', '/admin/careers/1/delete'] as $u) {
    $guest(); $a = $H($rq('POST', $u, ['title_en' => 'x']));
    check("careers admin POST $u: unauthenticated => login redirect, nothing written", $a->status === 302 && $loc($a) === '/admin/login');
}
$admin();
$r = $H($rq('POST', '/admin/careers', ['title_en' => 'Designer', 'description_en' => 'Desc']));
check('careers admin: a POST without CSRF is refused (403) and stores nothing', $r->status === 403 && $cnt('SELECT COUNT(*) FROM careers') === 0);
$r = $H($rq('POST', '/admin/careers', ['_csrf' => 'adm-token', 'title_en' => '', 'description_en' => '']));
check('careers admin: the English title and description are required (422)', $r->status === 422 && str_contains($r->body, 'English title and description are required') && $cnt('SELECT COUNT(*) FROM careers') === 0);
$r = $H($rq('POST', '/admin/careers', ['_csrf' => 'adm-token', 'title_en' => 'Front-end <b>Developer</b>', 'description_en' => "First paragraph <script>alert(1)</script>\n\nSecond paragraph", 'title_ru' => 'Разработчик', 'description_ru' => 'Описание', 'title_cs' => 'Jen název', 'description_cs' => '']));
check('careers admin: a title without a description in a non-English language is rejected', $r->status === 422 && $cnt('SELECT COUNT(*) FROM careers') === 0);
$r = $H($rq('POST', '/admin/careers', ['_csrf' => 'adm-token', 'title_en' => 'Front-end <b>Developer</b>', 'description_en' => "First paragraph <script>alert(1)</script>\n\nSecond paragraph", 'title_ru' => 'Разработчик', 'description_ru' => 'Описание на русском']));
$cid = (int) Database::pdo()->query('SELECT MAX(id) FROM careers')->fetchColumn();
check('careers admin: create saves a DRAFT and redirects to its edit page', $r->status === 303 && $loc($r) === "/admin/careers/$cid" && Careers::find($cid)['status'] === 'draft');
foreach (I18n::LOCALES as $lg) { $guest(); check("careers ($lg): a draft is NOT public", !str_contains($page("/$lg/careers"), 'Front-end') && str_contains($page("/$lg/careers"), I18n::get('careers.none_title', [], $lg))); }
$admin();
$edit = $H($rq('GET', "/admin/careers/$cid"));
check('careers admin: edit form shows all four languages and the status/timestamps', $edit->status === 200 && substr_count($edit->body, 'name="title_') === 4 && str_contains($edit->body, 'draft') && str_contains($edit->body, 'created '));
$r = $H($rq('POST', "/admin/careers/$cid/publish", ['_csrf' => 'adm-token']));
check('careers admin: publish sets status and published_at', $r->status === 303 && Careers::find($cid)['status'] === 'published' && Careers::find($cid)['published_at'] !== null);
$guest();
foreach (I18n::LOCALES as $lg) {
    $b = $page("/$lg/careers");
    $want = $lg === 'ru' ? 'Разработчик' : 'Front-end &lt;b&gt;Developer&lt;/b&gt;';
    check("careers ($lg): a published vacancy is shown (fallback to English where untranslated), escaped, with an apply mailto", str_contains($b, $want) && !str_contains($b, '<script>alert(1)</script>') && !str_contains($b, 'Front-end <b>') && str_contains($b, 'class="vacancy"') && str_contains($b, 'mailto:hello@pixelite.test?subject=') && !str_contains($b, I18n::get('careers.none_title', [], $lg)));
}
$admin();
$r = $H($rq('POST', "/admin/careers/$cid", ['_csrf' => 'adm-token', 'title_en' => 'Senior Developer', 'description_en' => 'Updated text']));
check('careers admin: edit updates the text and keeps it published', $r->status === 303 && Careers::find($cid)['titles']['en'] === 'Senior Developer' && Careers::find($cid)['status'] === 'published' && !isset(Careers::find($cid)['titles']['ru']));
check('careers: the update is visible publicly', str_contains($page('/en/careers'), 'Senior Developer'));
$r = $H($rq('GET', "/admin/careers/$cid/delete"));
check('careers admin: delete asks for confirmation first (GET deletes nothing)', $r->status === 200 && str_contains($r->body, 'Delete this vacancy?') && Careers::find($cid) !== null);
$r = $H($rq('POST', "/admin/careers/$cid/unpublish", ['_csrf' => 'adm-token']));
check('careers admin: unpublish hides it again', $r->status === 303 && !str_contains($page('/en/careers'), 'Senior Developer') && Careers::find($cid)['status'] === 'draft');
$r = $H($rq('POST', "/admin/careers/$cid/delete", []));
check('careers admin: deleting without CSRF is refused', $r->status === 403 && Careers::find($cid) !== null);
$r = $H($rq('POST', "/admin/careers/$cid/delete", ['_csrf' => 'adm-token']));
check('careers admin: confirmed delete removes the vacancy', $r->status === 303 && Careers::find($cid) === null);
check('careers admin: unknown vacancy ids are 404', $H($rq('GET', '/admin/careers/99999'))->status === 404);
$r = $H($rq('GET', '/admin/careers'));
check('careers admin: the list renders', $r->status === 200 && str_contains($r->body, 'Careers'));
$guest();

/* ---------------------------------------------------------------- Team */
echo "team\n";
$roles = ['en' => 'Founder / Team member', 'cs' => 'Zakladatel / člen týmu', 'ru' => 'Основатель / участник команды', 'uk' => 'Засновник / учасник команди'];
foreach (I18n::LOCALES as $lg) {
    $b = $page("/$lg/team");
    check("team ($lg): Emir Aliev with the localized role, one h1, no invented biography, no photo claims, no filter for one member", str_contains($b, 'Emir Aliev') && str_contains($b, e($roles[$lg])) && substr_count($b, '<h1') === 1 && !str_contains($b, 'team-card__bio') && !str_contains($b, 'data-team-filter') && !str_contains($b, '<img class="team-card__photo"'));
}
check('team: linked from the footer in every language, careers too', array_reduce(I18n::LOCALES, fn($c, $l) => $c && str_contains($page("/$l/"), 'href="/' . $l . '/team"') && str_contains($page("/$l/"), 'href="/' . $l . '/careers"'), true));
check('team: members come from config/team.php (adding one needs no redesign)', is_array(require Pixelite\Paths::root('config/team.php')) && count(require Pixelite\Paths::root('config/team.php')) >= 1);

/* ---------------------------------------------------------------- Dreamers placeholder */
echo "dreamers placeholder\n";
foreach (I18n::LOCALES as $lg) {
    foreach (['login', 'register'] as $pg) {
        $b = $page("/$lg/$pg");
        check("$pg ($lg): 'Continue with Dreamers' is a disabled button with an explanation, no link, no form target, no script", (bool) preg_match('#<button type="button" class="btn[^"]*auth-alt__btn" disabled aria-disabled="true"[^>]*>' . preg_quote(e(I18n::get('auth.dreamers', [], $lg)), '#') . '</button>#u', $b) && str_contains($b, e(I18n::get('auth.dreamers_note', [], $lg))) && !preg_match('#href="[^"]*dreamers#i', $b) && !preg_match('#action="[^"]*dreamers#i', $b));
    }
}

/* ---------------------------------------------------------------- accounts */
echo "customer accounts\n";
$reg = function (array $over = [], string $ip = '203.0.113.220', string $lang = 'en') use ($H, $rq): Pixelite\Response {
    $_SESSION = ['form_ts' => time() - 30, '_csrf' => 'reg-token'];
    return $H($rq('POST', "/$lang/register", $over + ['_csrf' => 'reg-token', 'name' => 'Petra Test', 'email' => 'petra@example.cz', 'password' => 'correct horse battery', 'password2' => 'correct horse battery', 'consent' => '1'], [], $ip));
};
$r = $reg();
$row = Customers::findByEmail('petra@example.cz');
check('register: success redirects to the login page (no auto-login) with a flash', $r->status === 303 && $loc($r) === '/en/login' && $row !== null && empty($_SESSION['customer_id']) && ($_SESSION['account_flash'] ?? '') === 'registered');
check('register: the password is stored only as a modern hash, never in plain text', $row !== null && $row['password_hash'] !== 'correct horse battery' && password_verify('correct horse battery', $row['password_hash']) && str_starts_with($row['password_hash'], '$') && !str_contains((string) file_get_contents("$tmp/test.sqlite"), 'correct horse battery'));
$r2 = $reg([], '203.0.113.221');
check('register: an already registered e-mail gets the SAME response (no enumeration) and no second account', $r2->status === 303 && $loc($r2) === $loc($r) && $cnt("SELECT COUNT(*) FROM customers WHERE email = 'petra@example.cz'") === 1);
$r3 = $reg(['email' => 'PETRA@Example.cz'], '203.0.113.222');
check('register: e-mail comparison is case-insensitive (no duplicate by capitalisation)', $cnt("SELECT COUNT(*) FROM customers WHERE lower(email) = 'petra@example.cz'") === 1);
foreach ([['password' => 'short', 'password2' => 'short', 'e' => 'password_short'], ['password2' => 'different password!', 'e' => 'password_mismatch'], ['email' => 'nope', 'e' => 'email_invalid'], ['consent' => '', 'e' => 'consent_required'], ['name' => 'x', 'e' => 'name_required'], ['password' => str_repeat('a', 73), 'password2' => str_repeat('a', 73), 'e' => 'password_long'], ['email' => 'pw@example.cz', 'password' => 'pw@example.cz', 'password2' => 'pw@example.cz', 'e' => 'password_email'], ['phone' => 'abc', 'e' => 'phone_invalid']] as $i => $case) {
    $e = $case['e']; unset($case['e']);
    $rr = $reg($case + ['email' => "v$i@example.cz"], '203.0.113.' . (230 + $i));
    check("register: validation '$e' => 422 with the localized message, nothing stored", $rr->status === 422 && str_contains($rr->body, e(I18n::get("auth.errors.$e"))) && Customers::findByEmail("v$i@example.cz") === null);
}
$_SESSION = ['form_ts' => time() - 30, '_csrf' => 'reg-token'];
$rr = $H($rq('POST', '/en/register', ['name' => 'No Csrf', 'email' => 'nocsrf@example.cz', 'password' => 'correct horse battery', 'password2' => 'correct horse battery', 'consent' => '1'], [], '203.0.113.240'));
check('register: no CSRF token => 403, nothing stored', $rr->status === 403 && Customers::findByEmail('nocsrf@example.cz') === null);
$rr = $reg(['hp_site' => 'bot', 'email' => 'bot@example.cz'], '203.0.113.241');
check('register: a filled honeypot is silently dropped', $rr->status === 303 && Customers::findByEmail('bot@example.cz') === null);
$_SESSION = ['form_ts' => time(), '_csrf' => 'reg-token'];
$rr = $H($rq('POST', '/en/register', ['_csrf' => 'reg-token', 'name' => 'Fast Bot', 'email' => 'fast@example.cz', 'password' => 'correct horse battery', 'password2' => 'correct horse battery', 'consent' => '1'], [], '203.0.113.242'));
check('register: an instant submit (no fill time) is dropped', $rr->status === 303 && Customers::findByEmail('fast@example.cz') === null);
$many = []; for ($i = 0; $i < 8; $i++) { $many[] = $reg(['email' => "rl$i@example.cz"], '203.0.113.243')->status; }
check('register: rate limited per connection (429 after 6 attempts/hour)', in_array(429, $many, true) && $many[0] === 303);

// login
$login = function (string $email, string $pw, string $ip, array $q = [], string $lang = 'en') use ($H, $rq): Pixelite\Response {
    $_SESSION = ['_csrf' => 'login-token'];
    return $H($rq('POST', "/$lang/login", ['_csrf' => 'login-token', 'email' => $email, 'password' => $pw], $q, $ip));
};
$a = $login('petra@example.cz', 'wrong password!!', '203.0.113.250');
$b = $login('nobody@example.cz', 'wrong password!!', '203.0.113.251');
$msg = e(I18n::get('auth.form_errors.invalid'));
check('login: wrong password and unknown e-mail give the identical generic 401 message', $a->status === 401 && $b->status === 401 && str_contains($a->body, $msg) && str_contains($b->body, $msg) && empty($_SESSION['customer_id']));
$_SESSION = []; $r = $H($rq('POST', '/en/login', ['email' => 'petra@example.cz', 'password' => 'correct horse battery'], [], '203.0.113.252'));
check('login: no CSRF token => 403 even with the right password', $r->status === 403 && empty($_SESSION['customer_id']));
$sidBefore = session_id();
$ok = $login('Petra@Example.cz', 'correct horse battery', '203.0.113.253');
check('login: success => 303 to the account, customer id in the session, CSRF token dropped, session id rotated', $ok->status === 303 && $loc($ok) === '/en/account' && ($_SESSION['customer_id'] ?? 0) === (int) $row['id'] && !isset($_SESSION['_csrf']) && session_id() !== $sidBefore);
foreach (['https://evil.example/', '//evil.example', '/admin', '/en/../admin', 'javascript:alert(1)', '/en/account/../../x', "/en/account\r\nSet-Cookie: x=1", '/xx/account'] as $n) {
    $rr = $login('petra@example.cz', 'correct horse battery', '203.0.113.254', ['next' => $n]);
    if ($loc($rr) !== '/en/account') { check("login: open redirect via next=$n", false, $loc($rr)); }
}
check('login: ?next= can never leave the customer\'s own account page (open redirect attempts all land on /en/account)', true);
check('login: a safe next (/cs/account) is honoured', $loc($login('petra@example.cz', 'correct horse battery', '203.0.113.255', ['next' => '/cs/account'])) === '/cs/account');
$statuses = []; for ($i = 0; $i < 12; $i++) { $statuses[] = $login('ratelimit@example.cz', 'x' . $i . 'wrongwrong', '203.0.113.17' . ($i % 2))->status; }
check('login: brute force is throttled (429) per account and per connection', in_array(429, $statuses, true));
$login('petra@example.cz', 'correct horse battery', '203.0.113.60');

// account page + isolation
$mkOrder = function (string $email, string $desc, ?int $customerId) use ($H, $rq): int {
    $_SESSION = ($customerId ? ['customer_id' => $customerId, 'customer_at' => time(), 'customer_since' => time()] : []) + ['form_ts' => time() - 30, '_csrf' => 'o-token'];
    $r = $H($rq('POST', '/en/order', ['_csrf' => 'o-token', 'name' => 'Owner', 'email' => $email, 'project_type' => 'website', 'budget' => 'unsure', 'timeframe' => 'flexible', 'description' => $desc, 'consent' => '1', 'package' => 'basic'], [], '203.0.113.' . random_int(100, 190)));
    return (int) Database::pdo()->query("SELECT MAX(id) FROM orders WHERE email = " . Database::pdo()->quote($email))->fetchColumn();
};
$ca = Customers::create(['name' => 'Alice Alpha', 'email' => 'alice@example.cz', 'company' => 'Alpha s.r.o.', 'phone' => '', 'password' => 'alice password 123'], 'en');
$cb = Customers::create(['name' => 'Bob Bravo', 'email' => 'bob@example.cz', 'company' => '', 'phone' => '', 'password' => 'bob password 12345'], 'en');
$oa = $mkOrder('alice@example.cz', 'ALPHA-SECRET-ORDER from Alice (signed in).', $ca);
$ob = $mkOrder('bob@example.cz', 'BRAVO-SECRET-ORDER from Bob (signed in).', $cb);
$og = $mkOrder('guest@example.cz', 'GUEST-ORDER sent without an account.', null);
$legacy = $mkOrder('alice@example.cz', 'LEGACY-ORDER same e-mail as Alice, sent while signed out.', null);
check('orders: signed-in orders carry the customer id; guest orders stay unlinked (guest checkout keeps working)', $cnt('SELECT customer_id FROM orders WHERE id = ?', [$oa]) === $ca && $cnt('SELECT customer_id FROM orders WHERE id = ?', [$ob]) === $cb && Database::pdo()->query("SELECT customer_id FROM orders WHERE id = $og")->fetchColumn() === null && $og > 0);
check('orders: a legacy order with the same e-mail address is NOT claimed automatically', Database::pdo()->query("SELECT customer_id FROM orders WHERE id = $legacy")->fetchColumn() === null);
$pa = Projects::create(['name' => 'ALPHA-PROJECT', 'notes' => 'internal alpha note', 'status' => 'draft', 'order_id' => $oa, 'customer_id' => $ca]);
$pb = Projects::create(['name' => 'BRAVO-PROJECT', 'notes' => 'internal bravo note', 'status' => 'draft', 'order_id' => $ob, 'customer_id' => $cb]);
$cust($ca); $acc = $H($rq('GET', '/en/account'))->body;
check('account (Alice): shows her profile, her order and her project – and nothing of Bob, guests or the legacy order', str_contains($acc, 'alice@example.cz') && str_contains($acc, 'Order #' . $oa) && str_contains($acc, 'ALPHA-PROJECT') && !str_contains($acc, 'bob@example.cz') && !str_contains($acc, 'Order #' . $ob) && !str_contains($acc, 'BRAVO') && !str_contains($acc, 'Order #' . $og) && !str_contains($acc, 'Order #' . $legacy) && !str_contains($acc, 'internal alpha note') && !str_contains($acc, 'password_hash'));
$cust($cb); $acc = $H($rq('GET', '/en/account'))->body;
check('account (Bob): sees only his own data', str_contains($acc, 'BRAVO-PROJECT') && !str_contains($acc, 'ALPHA') && !str_contains($acc, 'alice@example.cz'));
check('account: there is no route that takes a customer/order/project id (nothing to tamper with)', array_reduce(['/en/account/1', '/en/account/' . $ca, '/en/account?id=' . $ca, '/en/account?customer=' . $ca, '/en/orders/' . $oa, '/en/projects/' . $pa['id']], function ($c, $u) use ($H, $rq, $cust, $cb) { $cust($cb); $p = explode('?', $u); parse_str($p[1] ?? '', $q); $r = $H($rq('GET', $p[0], [], $q)); return $c && !str_contains($r->body, 'ALPHA') && !str_contains($r->body, 'alice@example.cz'); }, true));
$guest(); $r = $H($rq('GET', '/en/account'));
check('account: guests are redirected to the login page', $r->status === 302 && $loc($r) === '/en/login' && $r->body === '');
$r = $H($rq('POST', '/en/account', ['name' => 'Hacker', '_csrf' => 'x']));
check('account: a guest cannot update a profile', $r->status === 303 && $loc($r) === '/en/login' && $cnt("SELECT COUNT(*) FROM customers WHERE name = 'Hacker'") === 0);
$cust($ca);
$r = $H($rq('POST', '/en/account', ['name' => 'Alice Changed', 'company' => 'New Co', 'phone' => '+420 111 222 333', 'id' => (string) $cb, 'customer_id' => (string) $cb, 'email' => 'attacker@example.cz', 'status' => 'disabled', '_csrf' => 'cust-token']));
$a2 = Customers::find($ca); $b2 = Customers::find($cb);
check('account: profile update changes only the signed-in customer\'s name/company/phone; id, e-mail, status and other accounts cannot be changed through the form', $r->status === 303 && $a2['name'] === 'Alice Changed' && $a2['company'] === 'New Co' && $a2['email'] === 'alice@example.cz' && $a2['status'] === 'active' && $b2['name'] === 'Bob Bravo' && $b2['email'] === 'bob@example.cz');
$r = $H($rq('POST', '/en/account', ['name' => 'No Csrf Name', '_csrf' => 'wrong']));
check('account: profile update without a valid CSRF token => 403, unchanged', $r->status === 403 && Customers::find($ca)['name'] === 'Alice Changed');
$r = $H($rq('POST', '/en/account', ['name' => 'A', '_csrf' => 'cust-token']));
check('account: invalid profile values => 422 and unchanged', $r->status === 422 && Customers::find($ca)['name'] === 'Alice Changed');
$r = $H($rq('POST', '/en/logout', ['_csrf' => 'wrong']));
check('logout: without a valid CSRF token the customer stays signed in', !empty($_SESSION['customer_id']));
$r = $H($rq('POST', '/en/logout', ['_csrf' => $_SESSION['_csrf'] ?? 'cust-token']));
check('logout: POST with CSRF ends the customer session', $r->status === 303 && $loc($r) === '/en/login' && empty($_SESSION['customer_id']));
check('logout: GET is not a logout (no CSRF-able link)', $H($rq('GET', '/en/logout'))->status === 405 || $H($rq('GET', '/en/logout'))->status === 404);
$cust($ca); Customers::setStatus($ca, 'disabled');
$r = $H($rq('GET', '/en/account'));
check('a disabled account is locked out immediately, even with a live session', $r->status === 302 && $loc($r) === '/en/login' && empty($_SESSION['customer_id']));
check('a disabled account cannot sign in', $login('alice@example.cz', 'alice password 123', '203.0.113.61')->status === 401);
Customers::setStatus($ca, 'active');
$guest();
$cust($ca); $_SESSION['customer_at'] = time() - 7300;
$r = $H($rq('GET', '/en/account'));
check('customer sessions expire after 2 hours idle', $r->status === 302 && $loc($r) === '/en/login');
check('customer pages are never cached and never indexed', (function () use ($H, $rq, $cust, $ca) { $cust($ca); $r = $H($rq('GET', '/en/account')); return str_contains($r->headers['Cache-Control'] ?? '', 'no-store') && str_contains($r->body, 'noindex'); })());

// admin <-> customers
foreach (['/admin/customers', '/admin/customers/' . $ca] as $u) {
    $cust($ca); $r = $H($rq('GET', $u));
    check("admin customers: a signed-in CUSTOMER cannot open $u", $r->status === 302 && $loc($r) === '/admin/login' && $r->body === '');
}
$cust($ca); $r = $H($rq('POST', '/admin/orders/' . $legacy . '/link', ['customer_email' => 'alice@example.cz', '_csrf' => 'cust-token']));
check('admin link order: a customer cannot link orders to themselves', $r->status === 302 && Database::pdo()->query("SELECT customer_id FROM orders WHERE id = $legacy")->fetchColumn() === null);
$admin();
$r = $H($rq('POST', '/admin/orders/' . $legacy . '/link', ['customer_email' => 'alice@example.cz']));
check('admin link order: needs CSRF', $r->status === 403 && Database::pdo()->query("SELECT customer_id FROM orders WHERE id = $legacy")->fetchColumn() === null);
$r = $H($rq('POST', '/admin/orders/' . $legacy . '/link', ['customer_email' => 'alice@example.cz', '_csrf' => 'adm-token']));
check('admin link order: an administrator can deliberately link a legacy order', $r->status === 303 && $cnt('SELECT customer_id FROM orders WHERE id = ?', [$legacy]) === $ca);
$cust($ca); check('account: the deliberately linked order now appears for Alice only', str_contains($H($rq('GET', '/en/account'))->body, 'Order #' . $legacy));
$cust($cb); check('account: …and still not for Bob', !str_contains($H($rq('GET', '/en/account'))->body, 'Order #' . $legacy));
$admin();
$list = $H($rq('GET', '/admin/customers'))->body; $one = $H($rq('GET', '/admin/customers/' . $ca))->body;
check('admin customers: list and detail render without password hashes', str_contains($list, 'alice@example.cz') && !str_contains($list, '$2y$') && !str_contains($one, '$2y$') && !str_contains($one, 'password_hash'));
$r = $H($rq('POST', '/admin/customers/' . $ca . '/status', ['status' => 'disabled', '_csrf' => 'adm-token']));
check('admin customers: disable works (CSRF + admin)', $r->status === 303 && Customers::find($ca)['status'] === 'disabled');
$r = $H($rq('POST', '/admin/customers/' . $ca . '/status', ['status' => 'active']));
check('admin customers: changing status without CSRF is refused', $r->status === 403 && Customers::find($ca)['status'] === 'disabled');
Customers::setStatus($ca, 'active');

/* ---------------------------------------------------------------- ZIP reader + storage */
echo "project storage and uploads\n";
$makeZip = function (array $entries): string {
    $local = ''; $central = ''; $n = 0;
    foreach ($entries as $e) {
        $data = $e['data'] ?? ''; if ($data instanceof \Closure) { $data = $data(); }   // lazily built so big fixtures do not pile up in memory
        $method = $e['method'] ?? 8; $flags = $e['flags'] ?? 0;
        $comp = $method === 8 ? gzdeflate($data) : $data;
        $crc = $e['crc'] ?? (crc32($data) & 0xFFFFFFFF); $usize = $e['usize'] ?? strlen($data); $name = $e['name'];
        $off = strlen($local);
        $local .= "PK\x03\x04" . pack('vvvvvVVVvv', 20, $flags, $method, 0, 0x21, $crc, strlen($comp), $usize, strlen($name), 0) . $name . $comp;
        $mode = $e['mode'] ?? null;
        $central .= "PK\x01\x02" . pack('vvvvvv', $mode !== null ? (3 << 8) | 20 : 20, 20, $flags, $method, 0, 0x21) . pack('VVV', $crc, strlen($comp), $usize) . pack('vvvvv', strlen($name), 0, 0, 0, 0) . pack('VV', (($mode ?? 0) << 16) & 0xFFFFFFFF, $off) . $name;
        $n++;
    }
    return $local . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $n, $n, strlen($central), strlen($local), 0);
};
$zipFile = function (array $entries) use ($makeZip, $tmp): array {
    $f = tempnam($tmp, 'zip'); file_put_contents($f, $makeZip($entries));
    return ['name' => 'site.zip', 'tmp_name' => $f, 'size' => filesize($f), 'error' => UPLOAD_ERR_OK];
};
$goodZip = $zipFile([['name' => 'index.html', 'data' => '<h1>Hi</h1><link rel="stylesheet" href="css/a.css">'], ['name' => 'css/a.css', 'data' => 'body{color:red}'], ['name' => 'img/', 'data' => '', 'method' => 0], ['name' => 'img/p.png', 'data' => "\x89PNG\r\n\x1a\n", 'method' => 0]]);
check('zip reader: lists a valid archive and returns verified bytes', (function () use ($goodZip) { $e = ZipReader::entries($goodZip['tmp_name']); return count($e) === 4 && ZipReader::read($goodZip['tmp_name'], $e[1], 1000) === 'body{color:red}'; })());
$collect = fn(?array $files, ?array $zip) => ProjectStorage::collect($files, $zip, false);
[$ok1, $er1] = $collect(null, $goodZip);
check('collect: a good ZIP yields cleaned relative paths only', !$er1 && array_keys($ok1) === ['index.html', 'css/a.css', 'img/p.png']);
[$wrap] = $collect(null, $zipFile([['name' => 'mysite/index.html', 'data' => 'x'], ['name' => 'mysite/a.css', 'data' => 'y']]));
check('collect: a single wrapping folder in the ZIP is removed', array_keys($wrap) === ['index.html', 'a.css']);
foreach ([
    'path traversal ../' => [['name' => '../evil.html', 'data' => 'x']],
    'nested traversal' => [['name' => 'a/../../evil.html', 'data' => 'x']],
    'absolute path' => [['name' => '/etc/cron.d/x.html', 'data' => 'x']],
    'backslash path' => [['name' => 'a\\..\\b.html', 'data' => 'x']],
    'windows drive' => [['name' => 'C:/x.html', 'data' => 'x']],
    'PHP file' => [['name' => 'shell.php', 'data' => '<?php system($_GET[0]);']],
    'PHP double extension' => [['name' => 'shell.php.html.php', 'data' => 'x']],
    'phtml' => [['name' => 'a.phtml', 'data' => 'x']],
    '.htaccess' => [['name' => '.htaccess', 'data' => 'AddHandler x .html']],
    'hidden file' => [['name' => '.env', 'data' => 'SECRET=1']],
    'executable script' => [['name' => 'run.sh', 'data' => '#!/bin/sh']],
    'no extension' => [['name' => 'Makefile', 'data' => 'x']],
    'symlink entry' => [['name' => 'link.html', 'data' => '/etc/passwd', 'mode' => 0120777]],
    'device/special entry' => [['name' => 'dev.html', 'data' => '', 'mode' => 0020666]],
    'encrypted entry' => [['name' => 'a.html', 'data' => 'x', 'flags' => 1]],
    'unsupported method' => [['name' => 'a.html', 'data' => 'x', 'method' => 12]],
    'crc mismatch' => [['name' => 'a.html', 'data' => 'abc', 'crc' => 123]],
    'size mismatch (declared smaller)' => [['name' => 'a.html', 'data' => str_repeat('A', 5000), 'usize' => 10]],
    'zip bomb (declared huge)' => [['name' => 'a.html', 'data' => 'x', 'usize' => 900 * 1024 * 1024]],
    'control characters' => [['name' => "a\x01b.html", 'data' => 'x']],
] as $label => $entries) {
    $z = $zipFile($entries);
    [$got, $errs] = $collect(null, $z);
    check("zip: $label is refused (nothing accepted)", $errs !== [] && $got === [], json_encode($errs));
}
$z = $zipFile([['name' => 'a.html', 'data' => 'x']]); file_put_contents($z['tmp_name'], 'this is not a zip at all, just text');
[$g, $er] = $collect(null, $z);
check('zip: garbage input is refused', $er !== [] && $g === []);
$many = []; for ($i = 0; $i < 1600; $i++) { $many[] = ['name' => "f$i.html", 'data' => 'x', 'method' => 0]; }
[$g, $er] = $collect(null, $zipFile($many));
check('zip: too many entries is refused', $er !== [] && $g === []);
[$g, $er] = $collect(null, $zipFile([['name' => 'big.html', 'data' => fn() => str_repeat('A', ProjectStorage::MAX_FILE_BYTES + 1)]]));
check('zip: a single file over the per-file limit is refused (compression bomb guard)', $er !== [] && $g === []);
$sum = []; for ($i = 0; $i < 6; $i++) { $sum[] = ['name' => "chunk$i.html", 'data' => fn() => str_repeat('B', 9 * 1024 * 1024)]; }
[$g, $er] = $collect(null, $zipFile($sum));
check('zip: an archive that expands past the total project limit is refused', $er !== [] && $g === []);
foreach (['../x.html', '/x.html', 'a/../b.html', '.hidden.html', 'x.php', 'x.PHP', 'a b/.git/x.html', 'a//b.html', "a\0.html", 'dir/', 'x.html.', ' x.html', str_repeat('a', 120) . '.html', 'file'] as $bad) {
    if (ProjectStorage::cleanPath($bad) !== null) { check("cleanPath refuses '" . addcslashes($bad, "\0") . "'", false); }
}
check('cleanPath refuses every traversal / hidden / server-side / extension-less name', true);
check('cleanPath accepts ordinary website paths (unicode and spaces included)', ProjectStorage::cleanPath('index.html') === 'index.html' && ProjectStorage::cleanPath('assets/css/main.min.css') === 'assets/css/main.min.css' && ProjectStorage::cleanPath('_next/static/a.js') === '_next/static/a.js' && ProjectStorage::cleanPath('obrázky/Kavárna 1.jpg') === 'obrázky/Kavárna 1.jpg' && ProjectStorage::cleanPath('IMG.PNG') === 'IMG.PNG');
check('uploads: the guard against forged uploads works ($_FILES entries that were not really uploaded are rejected)', (function () use ($goodZip) { [$g, $e] = ProjectStorage::collect(['name' => ['a.html'], 'tmp_name' => [$goodZip['tmp_name']], 'size' => [10], 'error' => [0]], null, true); return $g === [] && $e !== []; })());
[$g, $e] = ProjectStorage::collect(['name' => ['../../etc/x.html', 'ok.html'], 'full_path' => ['../../etc/x.html', 'ok.html'], 'tmp_name' => [$goodZip['tmp_name'], $goodZip['tmp_name']], 'size' => [10, 10], 'error' => [0, 0]], null, false);
check('uploads: multi-file uploads with a hostile folder path are refused for that file', isset($g['ok.html']) && !isset($g['../../etc/x.html']) && $e !== []);
[$g, $e] = ProjectStorage::collect(['name' => ['a.html'], 'tmp_name' => [''], 'size' => [0], 'error' => [UPLOAD_ERR_INI_SIZE]], null, false);
check('uploads: PHP upload errors (e.g. over upload_max_filesize) become a clear message', $g === [] && str_contains(implode(' ', $e), 'upload limit'));

$p1 = Projects::create(['name' => 'Storage test', 'notes' => '', 'status' => 'draft', 'order_id' => null, 'customer_id' => null]);
$uid = $p1['uid'];
check('project: internal id is 16 random hex characters, unrelated to the numeric id and to the token', (bool) preg_match('/^[a-f0-9]{16}$/', $uid) && !str_contains($p1['token'], $uid) && $p1['token'] !== $uid);
check('project: share token is 43 URL-safe characters from 256 random bits; two projects never share one', (bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $p1['token']) && $p1['token'] !== $p1['uid'] && Projects::create(['name' => 'second', 'notes' => '', 'status' => 'draft', 'order_id' => null, 'customer_id' => null])['token'] !== $p1['token']);
check('project: a new project has its link OFF by default', (int) $p1['access_enabled'] === 0);
$errs = ProjectStorage::store($uid, $ok1, true);
check('storage: replace-mode writes the files under storage/projects/<uid>/site only, mode 0644 (never executable)', $errs === [] && is_file("$PR/$uid/site/index.html") && is_file("$PR/$uid/site/css/a.css") && (fileperms("$PR/$uid/site/index.html") & 0111) === 0 && count(ProjectStorage::inventory($uid)) === 3);
check('storage: nothing leaked outside the project folder (only <uid>/ exists under projects/)', array_values(array_diff(scandir("$PR"), ['.', '..', $uid])) === [] && !is_file("$tmp/evil.html") && !is_file("$PR/evil.html"));
check('storage: invalid uids can never address a folder', (function () { foreach (['..', '../x', 'ABCDEF0123456789', 'a b', '', str_repeat('a', 17), 'abc'] as $u) { try { ProjectStorage::dir($u); return false; } catch (\InvalidArgumentException) {} } return true; })());
$errs = ProjectStorage::store($uid, ['../outside.html' => 'x'], false);
check('storage: a hostile path handed straight to store() is refused and nothing is written', $errs !== [] && !is_file("$PR/$uid/outside.html") && !is_file("$PR/outside.html"));
$errs = ProjectStorage::store($uid, ['evil.php' => '<?php echo 1;'], false);
check('storage: server-side file types are refused at the storage layer too', $errs !== [] && !is_file("$PR/$uid/site/evil.php"));
$errs = ProjectStorage::store($uid, ['new.html' => 'n', 'Index.HTML' => 'case clash', 'index.html' => 'x'], false);
check('storage: names differing only by case are refused (case-insensitive file systems)', $errs !== []);
$errs = ProjectStorage::store($uid, ['extra.html' => 'extra'], false);
check('storage: add-mode keeps existing files and adds the new one', $errs === [] && count(ProjectStorage::inventory($uid)) === 4 && is_file("$PR/$uid/site/index.html"));
$errs = ProjectStorage::store($uid, ['index.html/child.html' => 'x'], false);
check('storage: a folder cannot be created where a file already exists', $errs !== [] && !is_dir("$PR/$uid/site/index.html"));
check('storage: unrelated files can still be added afterwards', ProjectStorage::store($uid, ['css.html' => 'ok'], false) === []);
$errs = ProjectStorage::store($uid, ['b' . 'ig.html' => str_repeat('x', ProjectStorage::MAX_FILE_BYTES + 1)], false);
check('storage: files over the per-file limit are refused', $errs !== []);
$errs = ProjectStorage::store($uid, ['only.html' => 'only'], true);
check('storage: replace-mode removes everything that was there before', $errs === [] && array_column(ProjectStorage::inventory($uid), 'path') === ['only.html'] && !is_file("$PR/$uid/site/index.html"));
check('storage: no leftover staging/temporary folders after replace', array_values(array_filter(scandir("$PR/$uid"), fn($n) => $n !== '.' && $n !== '..' && $n !== 'site')) === []);
ProjectStorage::store($uid, ['index.html' => '<!doctype html><title>Client site</title><p>Hello client</p><script>document.title="x"</script>', 'css/a.css' => 'p{color:red}', 'js/app.js' => 'console.log(1)', 'img/logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>', 'sub/index.html' => 'sub page', 'about/index.html' => 'about page'], true);
Projects::refreshStats($p1); $p1 = Projects::find((int) $p1['id']);
check('project: stats and the entry document are recorded', (int) $p1['file_count'] === 6 && $p1['entry'] === 'index.html' && (int) $p1['total_bytes'] > 0);

/* ---------------------------------------------------------------- isolated preview origin */
echo "preview origin and isolation\n";
$tok = $p1['token'];
$PH = 'preview.example.test';
$pv = fn(string $path, string $host = 'preview.example.test', string $m = 'GET', string $ip = '203.0.113.70') => $H($rq($m, $path, [], [], $ip, $host));
Projects::setAccess((int) $p1['id'], true);
Env::set('PREVIEW_ORIGIN', '');
check('preview is OFF (404 on every host) while PREVIEW_ORIGIN is not configured', $pv("/p/$tok/index.html")->status === 404 && $pv("/p/$tok/index.html", 'pixelite.cz')->status === 404 && !Preview::enabled() && Preview::url($p1) === '');
foreach (['https://pixelite.cz', 'https://pixelite.cz:8443', 'https://PIXELITE.cz', 'preview.example.test', 'ftp://preview.example.test', 'https://preview.example.test/path', 'https://user:pw@preview.example.test'] as $bad) {
    Env::set('PREVIEW_ORIGIN', $bad);
    if (Preview::enabled()) { check("PREVIEW_ORIGIN '$bad' must not enable previews", false); }
}
check('preview origin guard: the main host, another port of it, a path, credentials or a non-http scheme can never be a preview origin', true);
Env::set('PREVIEW_ORIGIN', "https://$PH");
check('preview origin: a different host is accepted; share URLs use it', Preview::enabled() && Preview::url($p1) === "https://$PH/p/$tok/");
$r = $pv("/p/$tok/index.html");
check('preview: a valid token serves the entry document (200, text/html, streamed from the project folder only)', $r->status === 200 && $r->file !== null && str_starts_with($r->headers['Content-Type'], 'text/html') && realpath($r->file) === realpath("$PR/$uid/site/index.html"));
check('preview: relative assets are served with the right fixed Content-Type', $pv("/p/$tok/css/a.css")->headers['Content-Type'] === 'text/css; charset=utf-8' && $pv("/p/$tok/js/app.js")->headers['Content-Type'] === 'text/javascript; charset=utf-8' && $pv("/p/$tok/img/logo.svg")->headers['Content-Type'] === 'image/svg+xml');
check('preview: behaves correctly with real request URIs (the router strips trailing slashes): /p/<token> redirects ONCE, /p/<token>/ serves, no loop', (function () use ($H, $tok, $PH) {
    $mk = fn(string $uri) => new Request('GET', '/' . trim(preg_replace('#/+#', '/', parse_url($uri, PHP_URL_PATH)), '/'), [], [], ['REMOTE_ADDR' => '203.0.113.71', 'HTTP_HOST' => $PH, 'REQUEST_URI' => $uri]);
    $a = $H($mk("/p/$tok")); $b = $H($mk("/p/$tok/")); $c = $H($mk("/p/$tok/index.html"));
    return $a->status === 302 && ($a->headers['Location'] ?? '') === "/p/$tok/" && $b->status === 200 && $b->file !== null && $c->status === 200;
})());
check('preview: /p/<token> redirects to /p/<token>/ (relative links), /p/<token>/ serves the entry, folder URLs serve index.html', $pv("/p/$tok")->status === 302 && ($pv("/p/$tok")->headers['Location'] ?? '') === "/p/$tok/" && $pv("/p/$tok/")->status === 200 && $pv("/p/$tok/sub/")->status === 200 && $pv("/p/$tok/about")->status === 200);
$h = $pv("/p/$tok/index.html")->headers;
check('preview headers: restrictive CSP (own files only, no external hosts, no framing, no form posts, no workers), nosniff, no Referer, no cache, DENY framing, COOP/CORP same-origin, noindex', str_contains($h['Content-Security-Policy'], "default-src 'self'") && str_contains($h['Content-Security-Policy'], "frame-ancestors 'none'") && str_contains($h['Content-Security-Policy'], "form-action 'none'") && str_contains($h['Content-Security-Policy'], "connect-src 'self'") && str_contains($h['Content-Security-Policy'], "worker-src 'none'") && str_contains($h['Content-Security-Policy'], "object-src 'none'") && !str_contains($h['Content-Security-Policy'], 'http') && !str_contains($h['Content-Security-Policy'], '*') && $h['X-Content-Type-Options'] === 'nosniff' && $h['Referrer-Policy'] === 'no-referrer' && str_contains($h['Cache-Control'], 'no-store') && $h['X-Frame-Options'] === 'DENY' && $h['Cross-Origin-Opener-Policy'] === 'same-origin' && $h['Cross-Origin-Resource-Policy'] === 'same-origin' && str_contains($h['X-Robots-Tag'], 'noindex') && str_contains($h['Strict-Transport-Security'] ?? '', 'max-age'));
check('preview headers: never a Set-Cookie, and the preview host does not start a session or touch the app\'s session', (function () use ($pv, $tok) { $_SESSION = ['marker' => 'x']; $r = $pv("/p/$tok/index.html"); return !isset($r->headers['Set-Cookie']) && $_SESSION === ['marker' => 'x']; })());
check('isolation: the main host does NOT serve previews (different origin by design)', $pv("/p/$tok/index.html", 'pixelite.cz')->status === 404 && $pv("/p/$tok/index.html", 'www.pixelite.cz')->status === 404);
foreach (['/', '/en/', '/en/order', '/admin', '/admin/login', '/en/login', '/en/account', '/sitemap.xml', '/robots.txt', '/assets/style.css'] as $u) {
    $r = $pv($u);
    if ($r->status !== 404 || str_contains($r->body, '<html') || isset($r->headers['Set-Cookie'])) { check("isolation: the preview host exposes nothing of the app: $u", false, (string) $r->status); }
}
check('isolation: the preview host serves ONLY /p/<token>/… (no site pages, admin, login, account or assets) and sets no cookies', true);
check('isolation: only GET/HEAD on the preview host (POST => 405)', $pv("/p/$tok/index.html", $PH, 'POST')->status === 405 && $pv("/p/$tok/index.html", $PH, 'HEAD')->status === 200);
foreach (["/p/$tok/../../storage/leads.sqlite", "/p/$tok/..%2f..%2fstorage%2fleads.sqlite", "/p/$tok/%2e%2e/%2e%2e/.env", "/p/$tok/./../x.html", "/p/$tok/.hidden.html", "/p/$tok/.git/config", "/p/$tok/index.html%00.php", "/p/$tok/a%5c..%5cb.html", "/p/$tok//etc/passwd", "/p/$tok/missing.html", "/p/$tok/css/", "/p/$tok/css"] as $u) {
    foreach ([$u, rawurldecode($u)] as $variant) {
        $r = $pv($variant);
        if ($r->status !== 404 || $r->file !== null) { check("preview traversal attempt $variant", false, (string) $r->status); }
    }
}
check('preview: traversal, dotfiles, NUL bytes, backslashes and missing files are all 404 and never read outside the site folder', true);
// files that exist on disk but must never be served
file_put_contents("$PR/$uid/site/danger.php", '<?php echo "executed";'); file_put_contents("$PR/$uid/site/.secret.html", 'hidden'); file_put_contents("$PR/$uid/site/noext", 'x');
@symlink("$tmp/test.sqlite", "$PR/$uid/site/link.html"); @symlink($tmp, "$PR/$uid/site/linkdir");
check('preview: a PHP file that somehow exists in the folder is never executed or served', $pv("/p/$tok/danger.php")->status === 404);
check('preview: hidden files and extension-less files are not served', $pv("/p/$tok/.secret.html")->status === 404 && $pv("/p/$tok/noext")->status === 404);
check('preview: symlinks (file or folder) pointing outside are not followed', $pv("/p/$tok/link.html")->status === 404 && $pv("/p/$tok/linkdir/test.sqlite")->status === 404 && ProjectStorage::resolve($uid, 'link.html') === null);
check('storage inventory ignores symlinks and hidden files', !in_array('link.html', array_column(ProjectStorage::inventory($uid), 'path'), true) && !in_array('.secret.html', array_column(ProjectStorage::inventory($uid), 'path'), true));
check('preview: malformed tokens (wrong length/characters) are 404', array_reduce(['short', str_repeat('A', 42), str_repeat('A', 44), str_repeat('!', 43), "x'; DROP TABLE projects;--"], fn($c, $t) => $c && $pv("/p/$t/index.html")->status === 404, true) && $cnt('SELECT COUNT(*) FROM projects') >= 1);
check('preview: an unknown (well-formed) token is 404 with no hint', $pv('/p/' . Projects::newToken() . '/index.html')->status === 404);
$p2 = Projects::create(['name' => 'Other client', 'notes' => '', 'status' => 'draft', 'order_id' => null, 'customer_id' => null]); ProjectStorage::store($p2['uid'], ['index.html' => 'OTHER-CLIENT-SECRET'], true); Projects::setAccess((int) $p2['id'], true);
check('preview: one project\'s token cannot read another project\'s files, whatever the path', $pv("/p/{$p2['token']}/index.html")->file !== null && !str_contains((string) file_get_contents($pv("/p/{$p2['token']}/index.html")->file), 'Hello client') && $pv("/p/$tok/../{$p2['uid']}/site/index.html")->status === 404 && $pv("/p/{$p2['token']}/css/a.css")->status === 404);
Projects::setAccess((int) $p1['id'], false);
check('revocation: switching the link off takes effect immediately (404)', $pv("/p/$tok/index.html")->status === 404 && $pv("/p/$tok/css/a.css")->status === 404);
Projects::setAccess((int) $p1['id'], true);
check('…and switching it on again restores the same link', $pv("/p/$tok/index.html")->status === 200);
Projects::regenerateToken((int) $p1['id']); $p1 = Projects::find((int) $p1['id']);
check('token regeneration: the old link stops working, the new one works, the token changed', $p1['token'] !== $tok && $pv("/p/$tok/index.html")->status === 404 && $pv("/p/{$p1['token']}/index.html")->status === 200);
$tok2 = $p1['token'];
$misses = []; for ($i = 0; $i < 70; $i++) { $misses[] = $pv('/p/' . Projects::newToken() . '/x.html', $PH, 'GET', '203.0.113.99')->status; }
check('preview: repeated invalid-token guessing from one connection is throttled (429)', in_array(429, $misses, true));
check('preview: a throttled guesser does not lock out other visitors', $pv("/p/$tok2/index.html", $PH, 'GET', '203.0.113.98')->status === 200);

/* ---------------------------------------------------------------- admin: projects */
echo "admin projects\n";
foreach (['/admin/projects', '/admin/projects/new', '/admin/projects/' . $p1['id'], '/admin/projects/' . $p1['id'] . '/delete'] as $u) {
    $guest(); $a = $H($rq('GET', $u)); $cust($ca); $b = $H($rq('GET', $u));
    check("projects admin GET $u: guests and signed-in customers are sent to the staff login", $a->status === 302 && $loc($a) === '/admin/login' && $b->status === 302 && $loc($b) === '/admin/login' && $a->body === '' && $b->body === '');
}
foreach (['', '/upload', '/access', '/token', '/delete'] as $suffix) {
    $guest(); $a = $H($rq('POST', '/admin/projects/' . $p1['id'] . $suffix, ['_csrf' => 'adm-token', 'access' => 'off']));
    $cust($ca); $b = $H($rq('POST', '/admin/projects/' . $p1['id'] . $suffix, ['_csrf' => 'cust-token', 'access' => 'off']));
    check("projects admin POST $suffix: guests and customers cannot change anything", $a->status === 302 && $b->status === 302 && Projects::find((int) $p1['id']) !== null && (int) Projects::find((int) $p1['id'])['access_enabled'] === 1 && Projects::find((int) $p1['id'])['token'] === $tok2);
}
$admin();
foreach (['/access' => ['access' => 'off'], '/token' => [], '/delete' => [], '' => ['name' => 'Hacked'], '/upload' => []] as $suffix => $post) {
    $r = $H($rq('POST', '/admin/projects/' . $p1['id'] . $suffix, $post));
    check("projects admin: POST $suffix without CSRF is rejected (403) and changes nothing", $r->status === 403 && Projects::find((int) $p1['id'])['name'] === 'Storage test' && (int) Projects::find((int) $p1['id'])['access_enabled'] === 1 && Projects::find((int) $p1['id'])['token'] === $tok2);
}
$r = $H($rq('POST', '/admin/projects', ['_csrf' => 'adm-token', 'name' => '', 'order_id' => '999999', 'customer_email' => 'nobody@example.cz']));
check('projects admin: create validates the name, the order and the customer (422)', $r->status === 422 && str_contains($r->body, 'name is required') && str_contains($r->body, 'order does not exist') && str_contains($r->body, 'No customer account'));
$before = $cnt('SELECT COUNT(*) FROM projects');
$r = $H($rq('POST', '/admin/projects', ['_csrf' => 'adm-token', 'name' => 'Created <i>via</i> admin', 'notes' => 'private note', 'order_id' => (string) $oa, 'customer_email' => 'alice@example.cz']));
$pid = (int) Database::pdo()->query('SELECT MAX(id) FROM projects')->fetchColumn(); $pc = Projects::find($pid);
check('projects admin: create stores a project linked to an order and a customer, link OFF, redirect to its page', $r->status === 303 && $cnt('SELECT COUNT(*) FROM projects') === $before + 1 && $loc($r) === "/admin/projects/$pid" && (int) $pc['order_id'] === $oa && (int) $pc['customer_id'] === $ca && (int) $pc['access_enabled'] === 0 && preg_match('/^[a-f0-9]{16}$/', $pc['uid']));
$pg = $H($rq('GET', "/admin/projects/$pid"));
check('projects admin: the page escapes names, explains the bearer-link risk and embeds NO iframe/script from the project', $pg->status === 200 && str_contains($pg->body, 'Created &lt;i&gt;via&lt;/i&gt; admin') && str_contains($pg->body, 'Treat the link like a password') && !str_contains($pg->body, '<iframe') && !str_contains($pg->body, '<i>via</i>'));
check('projects admin: with the preview origin configured the share URL is shown (only when the link is on)', !str_contains($pg->body, '/p/' . $pc['token']));
$r = $H($rq('POST', "/admin/projects/$pid/access", ['_csrf' => 'adm-token', 'access' => 'on']));
$pg = $H($rq('GET', "/admin/projects/$pid"));
check('projects admin: switching the link on shows the share URL on the preview origin (never on the admin origin)', $r->status === 303 && str_contains($pg->body, "https://$PH/p/{$pc['token']}/") && !str_contains($pg->body, 'href="/p/'));
$r = $H($rq('POST', "/admin/projects/$pid/upload", ['_csrf' => 'adm-token', 'mode' => 'replace'], [], '203.0.113.9', 'pixelite.cz', ['files' => ['name' => ['index.html'], 'tmp_name' => [$goodZip['tmp_name']], 'size' => [5], 'error' => [0]]]));
check('projects admin: the upload endpoint refuses files PHP did not receive as real uploads', $r->status === 422 && ProjectStorage::inventory($pc['uid']) === []);
$r = $H($rq('POST', "/admin/projects/$pid/upload", ['_csrf' => 'adm-token'], [], '203.0.113.9', 'pixelite.cz', []));
check('projects admin: an upload with no file is a clear error, not a crash', $r->status === 422 && str_contains($r->body, 'No files were provided'));
$errs = ProjectStorage::store($pc['uid'], ['index.html' => 'created via admin'], true); Projects::refreshStats($pc);
$r = $H($rq('POST', "/admin/projects/$pid", ['_csrf' => 'adm-token', 'name' => 'Renamed', 'status' => 'in_review', 'notes' => 'n', 'order_id' => '', 'customer_email' => '']));
check('projects admin: edit updates name/status/notes and can remove the associations', $r->status === 303 && Projects::find($pid)['name'] === 'Renamed' && Projects::find($pid)['status'] === 'in_review' && Projects::find($pid)['order_id'] === null && Projects::find($pid)['customer_id'] === null);
$tokOld = Projects::find($pid)['token'];
$r = $H($rq('POST', "/admin/projects/$pid/token", ['_csrf' => 'adm-token']));
check('projects admin: regenerate token works through the admin', $r->status === 303 && Projects::find($pid)['token'] !== $tokOld);
$r = $H($rq('GET', "/admin/projects/$pid/delete"));
check('projects admin: delete asks for confirmation first (GET deletes nothing)', $r->status === 200 && str_contains($r->body, 'Delete this project?') && Projects::find($pid) !== null && is_dir("$PR/{$pc['uid']}"));
$tokNow = Projects::find($pid)['token'];
$r = $H($rq('POST', "/admin/projects/$pid/delete", ['_csrf' => 'adm-token']));
check('projects admin: confirmed delete removes the row AND every file; the old link is dead', $r->status === 303 && Projects::find($pid) === null && !is_dir("$PR/{$pc['uid']}") && $pv("/p/$tokNow/index.html")->status === 404);
check('projects admin: unknown project ids are 404', $H($rq('GET', '/admin/projects/987654'))->status === 404);
$cust($ca); $acc = $H($rq('GET', '/en/account'))->body;
Projects::setAccess((int) $pa['id'], true); ProjectStorage::store($pa['uid'], ['index.html' => 'alpha'], true); Projects::refreshStats($pa);
$cust($ca); $acc = $H($rq('GET', '/en/account'))->body;
check('account: a customer sees the preview link of their OWN project (on the preview origin), not Bob\'s', str_contains($acc, "https://$PH/p/" . Projects::find((int) $pa['id'])['token'] . '/') && !str_contains($acc, Projects::find((int) $pb['id'])['token']) && !str_contains($acc, 'BRAVO'));
Env::set('PREVIEW_ORIGIN', '');
$cust($ca); $acc = $H($rq('GET', '/en/account'))->body;
check('account: without a preview origin no link is offered (the project shows as not available)', !str_contains($acc, '/p/') && str_contains($acc, e(I18n::get('account.preview_off'))));
$admin(); check('admin projects list: shows the "previews are off" warning when no origin is configured', str_contains($H($rq('GET', '/admin/projects'))->body, 'Browser previews are switched off'));

/* ---------------------------------------------------------------- headers + secrets */
echo "headers and leaks\n";
$guest();
foreach (['/en/careers', '/en/team', '/en/login', '/en/register'] as $u) {
    $r = $H($rq('GET', $u));
    $csp = $r->headers['Content-Security-Policy'] ?? '';
    check("$u: strict site CSP (no inline script/style, same-origin only, not frameable), nosniff, no inline handlers or scripts", str_contains($csp, "default-src 'self'") && str_contains($csp, "frame-ancestors 'none'") && !str_contains($csp, 'unsafe') && !preg_match('/<script(?![^>]*\bsrc=)(?![^>]*ld\+json)[^>]*>|\son[a-z]+="|\sstyle="/', $r->body) && ($r->headers['X-Content-Type-Options'] ?? '') === 'nosniff');
}
check('login/register pages are noindex and not cached', (function () use ($H, $rq, $guest) { $guest(); foreach (['/en/login', '/en/register'] as $u) { $r = $H($rq('GET', $u)); if (!str_contains($r->body, 'content="noindex,nofollow"') || !str_contains($r->headers['Cache-Control'] ?? '', 'no-store')) return false; } return true; })());
check('no Endora/advertising markup is rendered by any of the application\'s own templates', (function () use ($pagesAll, $H, $rq, $guest) { foreach ($pagesAll as $pg) { $guest(); $b = $H($rq('GET', "/en/$pg"))->body; if (preg_match('#endora\.(cz|sk)|freehosting|FreeHosting#i', preg_replace('#<script[^>]*endora-banner[^>]*></script>#', '', $b))) return false; } return true; })());
check('.env.example documents PREVIEW_ORIGIN and the project limits', str_contains((string) file_get_contents(Pixelite\Paths::root('.env.example')), 'PREVIEW_ORIGIN'));
check('storage/projects is outside the web root and never part of the Endora artifact (storage/ is created by PHP, never uploaded)', !str_contains((string) file_get_contents(Pixelite\Paths::root('scripts/build-endora.php')), 'storage/projects') && is_dir(Pixelite\Paths::root('public')) && !is_dir(Pixelite\Paths::root('public/projects')));
check('no password or token ever reaches the application log', (function () use ($tmp) { $log = (string) @file_get_contents(Pixelite\Paths::storage('logs/app.log')); return !str_contains($log, 'correct horse battery') && !str_contains($log, 'alice password') && !preg_match('#/p/[A-Za-z0-9_-]{43}#', $log); })());

check('session cookie: __Host- prefixed over HTTPS (cannot be shadowed from a sibling preview host), plain name over HTTP', Pixelite\Session::cookieName(new Request('GET', '/', [], [], ['HTTPS' => 'on'])) === '__Host-pixelite' && Pixelite\Session::cookieName(new Request('GET', '/', [], [], [])) === 'pixelite');
check('preview: a same-registrable-domain host is flagged as weaker isolation, a separate domain is not', (function () { Env::set('APP_URL', 'https://pixelite.cz'); Env::set('PREVIEW_ORIGIN', 'https://preview.pixelite.cz'); $a = Preview::sharesSite(); Env::set('PREVIEW_ORIGIN', 'https://pixelite-preview.cz'); $b = Preview::sharesSite(); Env::set('PREVIEW_ORIGIN', ''); return $a === true && $b === false; })());
check('cookie policy shows the real session cookie name over HTTPS', str_contains($H(new Request('GET', '/en/cookies', [], [], ['HTTPS' => 'on', 'REMOTE_ADDR' => '203.0.113.5']))->body, '<code>__Host-pixelite</code>'));
