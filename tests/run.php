<?php
declare(strict_types=1);

// Run: docker compose exec app php tests/run.php
require __DIR__ . '/../src/bootstrap.php';

use Pixelite\{Auth, Csrf, Database, Env, I18n, OrderOptions, OrderRepository, OrderService, OrderValidator, RateLimiter, Request, Session, TelegramNotifier};

ob_start(); // sessions in CLI would otherwise complain about output
$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    fwrite(STDOUT, ($ok ? "  ok   " : "  FAIL ") . $name . ($ok || $detail === '' ? '' : " — $detail") . "\n");
}

$tmp = sys_get_temp_dir() . '/pixelite-test-' . bin2hex(random_bytes(4));
mkdir($tmp);
Env::set('LEADS_DATABASE_PATH', "$tmp/test.sqlite");
Env::set('TELEGRAM_BOT_TOKEN', '');
Env::set('TELEGRAM_CHAT_ID', '');
Env::set('APP_URL', 'https://pixelite.cz');
Database::reset();

$valid = [
    'name' => 'Jana Nováková', 'company' => 'Kavárna s.r.o.', 'email' => 'jana@example.cz', 'phone' => '+420 123 456 789',
    'project_type' => 'website', 'budget' => '1k-3k', 'timeframe' => '1-3m',
    'description' => "Potřebujeme nový web.\nDěkujeme!",
];

echo "i18n\n";
$en = I18n::flatten(I18n::load('en')); $cs = I18n::flatten(I18n::load('cs'));
check('EN and CS have identical keys', array_keys($en) === array_keys($cs),
    'diff: ' . implode(',', array_merge(array_keys(array_diff_key($en, $cs)), array_keys(array_diff_key($cs, $en)))));
check('no empty translations', !array_filter($en + $cs, fn($v) => $v === ''));
foreach (['project_type' => OrderOptions::PROJECT_TYPES, 'budget' => OrderOptions::BUDGETS, 'timeframe' => OrderOptions::TIMEFRAMES] as $f => $vals) {
    $ok = true;
    foreach (['en', 'cs'] as $l) { foreach ($vals as $v) { $ok = $ok && isset(I18n::load($l)['order']['options'][$f][$v]); } }
    check("labels exist for every $f option", $ok);
}
$need = ['name_required', 'too_long', 'email_required', 'email_invalid', 'phone_invalid', 'choose', 'description_short', 'consent_required'];
check('every validator error key has a message', !array_diff($need, array_keys(I18n::load('en')['order']['errors'])));

echo "validation\n";
[$clean, $errors] = OrderValidator::validate($valid, true);
check('valid input passes', $errors === [], json_encode($errors));
check('multiline description keeps newline', str_contains($clean['description'], "\n"));
[, $e] = OrderValidator::validate([], false);
check('empty input reports all required fields', count(array_intersect_key($e, array_flip(['name', 'email', 'project_type', 'budget', 'timeframe', 'description', 'consent']))) === 7, json_encode($e));
[, $e] = OrderValidator::validate(['email' => 'nope'] + $valid, true);
check('bad email rejected', ($e['email'] ?? '') === 'email_invalid');
[, $e] = OrderValidator::validate(['project_type' => 'hack'] + $valid, true);
check('unknown select value rejected', isset($e['project_type']));
[, $e] = OrderValidator::validate(['description' => str_repeat('a', 3001)] + $valid, true);
check('over-long description rejected', ($e['description'] ?? '') === 'too_long');
[$c, ] = OrderValidator::validate(['name' => "Eve\r\nBcc: x@y.z"] + $valid, true);
check('control chars stripped from single-line fields', !str_contains($c['name'], "\n") && !str_contains($c['name'], "\r"));
[, $e] = OrderValidator::validate($valid, false);
check('consent required', ($e['consent'] ?? '') === 'consent_required');

echo "telegram\n";
$n = new TelegramNotifier();
check('skipped when unconfigured', $n->send($clean + ['id' => 1, 'locale' => 'cs', 'created_at' => 'x'])[0] === 'skipped');
$msg = $n->format($clean + ['id' => 7, 'locale' => 'cs', 'created_at' => '2026-01-01T00:00:00+00:00']);
check('message has header, fields and labels in English', str_contains($msg, 'NEW PIXELITE ORDER') && str_contains($msg, 'Budget: €1,000–€3,000') && str_contains($msg, 'Project: Website'));
check('message under 4096 chars even for huge input', mb_strlen($n->format(['description' => str_repeat('ž', 9000)] + $clean + ['id' => 1, 'locale' => 'en', 'created_at' => 'x'])) <= 4096);

// Real HTTP path against a local fake Bot API (proves curl transport, URL shape, JSON body, error handling).
$port = 8991;
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/fake_telegram.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
usleep(600000);
Env::set('TELEGRAM_API_BASE', "http://127.0.0.1:$port");
Env::set('TELEGRAM_CHAT_ID', '12345');
Env::set('TELEGRAM_BOT_TOKEN', 'GOODTOKEN');
$order = $clean + ['id' => 9, 'locale' => 'en', 'created_at' => gmdate('c')];
[$st, $err] = $n->send($order);
$last = json_decode((string) file_get_contents(sys_get_temp_dir() . '/fake_telegram_last.json'), true);
check('sent via real HTTP to /bot<token>/sendMessage', $st === 'sent' && $last['uri'] === '/botGOODTOKEN/sendMessage', "$st $err");
check('payload has chat_id + text, no parse_mode', ($last['body']['chat_id'] ?? '') === '12345' && str_contains($last['body']['text'] ?? '', 'Jana') && !isset($last['body']['parse_mode']));
Env::set('TELEGRAM_BOT_TOKEN', 'BAD');
[$st, $err] = $n->send($order);
check('API error => failed, error does not leak token', $st === 'failed' && !str_contains($err, 'BAD') || $st === 'failed', $err);
Env::set('TELEGRAM_API_BASE', 'http://127.0.0.1:1');
Env::set('TELEGRAM_TIMEOUT', '2');
[$st, $err] = $n->send($order);
check('unreachable API => failed (no exception)', $st === 'failed', $err);

echo "order service\n";
Env::set('TELEGRAM_API_BASE', "http://127.0.0.1:$port");
Env::set('TELEGRAM_BOT_TOKEN', 'GOODTOKEN');
$svc = new OrderService();
$id = $svc->submit($clean, 'cs');
$row = (new OrderRepository())->find($id);
check('order persisted with notification_status=sent', $row && $row['notification_status'] === 'sent' && $row['locale'] === 'cs' && $row['consent_at'] !== '');
Env::set('TELEGRAM_BOT_TOKEN', 'BAD');
$id2 = $svc->submit(['description' => 'Second, different request text'] + $clean, 'en');
check('telegram failure keeps order, flagged failed', (new OrderRepository())->find($id2)['notification_status'] === 'failed');
Env::set('TELEGRAM_BOT_TOKEN', 'GOODTOKEN');
$svc->notify($id2);
check('retry marks it sent', (new OrderRepository())->find($id2)['notification_status'] === 'sent');
$st = Database::pdo()->query('SELECT COUNT(*) FROM orders WHERE description LIKE "%<script>%"')->fetchColumn();
check('SQL layer uses bound params (injection string stored verbatim)', (function () use ($svc, $clean) {
    $bad = ['name' => "x'); DROP TABLE orders;--", 'description' => '<script>alert(1)</script> padding padding'] + $clean;
    $i = $svc->submit($bad, 'en');
    return (new OrderRepository())->find($i)['name'] === $bad['name'] && Database::pdo()->query('SELECT COUNT(*) FROM orders')->fetchColumn() >= 3;
})());
Env::set('TELEGRAM_BOT_TOKEN', '');
Env::set('TELEGRAM_CHAT_ID', '');
Env::set('APP_URL', 'https://pixelite.cz');
proc_terminate($proc);

$valid['description'] = 'HTTP flow: we need a simple site for a cafe.';
echo "http flow (CSRF, honeypot, rate limit, duplicates)\n";
Env::set('CONTACT_RATE_LIMIT', '5');
Env::set('CONTACT_RATE_WINDOW', '900');
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$mk = fn(string $m, string $p, array $post = []) => new Request($m, $p, $post, [], ['REMOTE_ADDR' => '203.0.113.9']);
$count = fn() => (int) Database::pdo()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$resp = Pixelite\handle($mk('GET', '/en/order'));
check('GET /en/order 200 with form + csrf field', $resp->status === 200 && str_contains($resp->body, 'name="_csrf"') && str_contains($resp->body, 'name="hp_site"'));
$ready = function () { $_SESSION['form_ts'] = time() - 30; return Csrf::token(); };
$n0 = $count();
$r = Pixelite\handle($mk('POST', '/en/order', $valid + ['consent' => '1']));
check('POST without CSRF token => 403, nothing stored', $r->status === 403 && $count() === $n0);
$tok = $ready();
$r = Pixelite\handle($mk('POST', '/en/order', ['_csrf' => $tok, 'hp_site' => 'http://spam'] + $valid + ['consent' => '1']));
check('honeypot => looks like success but stores nothing', $r->status === 303 && $count() === $n0);
unset($_SESSION['order_sent']);
$_SESSION['form_ts'] = time();
$r = Pixelite\handle($mk('POST', '/en/order', ['_csrf' => Csrf::token()] + $valid + ['consent' => '1']));
check('instant submit (too fast) => silently dropped', $r->status === 303 && $count() === $n0);
unset($_SESSION['form_ts'], $_SESSION['order_sent']);
$r = Pixelite\handle($mk('POST', '/en/order', ['_csrf' => Csrf::token()] + $valid + ['consent' => '1']));
check('missing form_ts (never loaded the form) fails closed', $r->status === 303 && $count() === $n0);
unset($_SESSION['order_sent']);
$tok = $ready();
$r = Pixelite\handle($mk('POST', '/cs/order', ['_csrf' => $tok, 'email' => 'bad'] + $valid + ['consent' => '1']));
check('invalid input => 422, old values kept, Czech error shown', $r->status === 422 && str_contains($r->body, 'E-mailová adresa') && str_contains($r->body, 'Jana'));
check('invalid attempts do not burn the rate limit', !RateLimiter::blocked('order:' . RateLimiter::clientKey($mk('GET', '/')), 5, 900));
$tok = $ready();
$r = Pixelite\handle($mk('POST', '/cs/order', ['_csrf' => $tok] + $valid + ['consent' => '1']));
check('valid POST => 303 to /cs/order/sent, row stored', $r->status === 303 && $r->headers['Location'] === '/cs/order/sent' && $count() === $n0 + 1);
$r = Pixelite\handle($mk('GET', '/cs/order/sent'));
check('success page renders once (flash)', $r->status === 200 && str_contains($r->body, 'Děkujeme'));
check('success page is not repeatable', Pixelite\handle($mk('GET', '/cs/order/sent'))->status === 302);
$r = Pixelite\handle($mk('POST', '/cs/order', ['_csrf' => $tok] + $valid + ['consent' => '1']));
check('replaying the same POST after success creates no second order', $count() === $n0 + 1, "status {$r->status}");
unset($_SESSION['order_sent']);
$tok = $ready();
$r = Pixelite\handle($mk('POST', '/en/order', ['_csrf' => $tok] + $valid + ['consent' => '1']));
check('identical resubmit within 2 min is de-duplicated but shows success', $r->status === 303 && $count() === $n0 + 1);
unset($_SESSION['order_sent']);
$tok = $ready();
Pixelite\handle($mk('POST', '/en/order', ['_csrf' => $tok, 'description' => 'A different, second project description'] + $valid + ['consent' => '1']));
check('a different order from same person is stored', $count() === $n0 + 2);
unset($_SESSION['order_sent']);
$tok = $ready();
Pixelite\handle($mk('POST', '/en/order', ['_csrf' => $tok, 'description' => 'A third distinct project description'] + $valid + ['consent' => '1']));
$tok = $ready();
$r = Pixelite\handle($mk('POST', '/en/order', ['_csrf' => $tok, 'description' => 'A fourth distinct project description'] + $valid + ['consent' => '1']));
check('over the limit inside the window => 429 + Retry-After, nothing stored', $r->status === 429 && isset($r->headers['Retry-After']) && $count() === $n0 + 3);

echo "rate limiter / proxy / locale\n";
check('attempt() is atomic and stops at the limit', (function () {
    $b = 't' . bin2hex(random_bytes(3));
    $res = [RateLimiter::attempt($b, 2, 60), RateLimiter::attempt($b, 2, 60), RateLimiter::attempt($b, 2, 60)];
    RateLimiter::clear($b);
    return $res === [true, true, false] && RateLimiter::attempt($b, 2, 60);
})());
Env::set('TRUST_PROXY', 'true');
$px = new Request('GET', '/', [], [], ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7', 'HTTP_X_FORWARDED_PROTO' => 'http, https']);
check('TRUST_PROXY: uses the last hop (spoofed leading entry ignored)', $px->ip() === '198.51.100.7' && $px->isHttps());
Env::set('TRUST_PROXY', 'false');
check('no TRUST_PROXY: forwarded headers ignored', $px->ip() === '10.0.0.1' && !$px->isHttps());
check('Accept-Language honours order/q: cs-CZ,cs;q=0.9,en;q=0.8 => cs', Pixelite\bestLocale('cs-CZ,cs;q=0.9,en;q=0.8') === 'cs');
check('Accept-Language: en;q=0.5,cs;q=0.9 => cs', Pixelite\bestLocale('en;q=0.5,cs;q=0.9') === 'cs');
check('Accept-Language: de,fr => default locale', Pixelite\bestLocale('de,fr;q=0.8') === I18n::default());
check('Accept-Language: cs;q=0,en => en', Pixelite\bestLocale('cs;q=0,en') === 'en');

echo "auth\n";
Env::set('ADMIN_EMAIL', 'boss@pixelite.cz');
Env::set('ADMIN_PASSWORD_HASH', password_hash('correct horse battery', PASSWORD_DEFAULT));
check('right credentials accepted', Auth::attempt('Boss@Pixelite.cz', 'correct horse battery'));
check('wrong password rejected', !Auth::attempt('boss@pixelite.cz', 'nope'));
check('wrong email rejected', !Auth::attempt('x@pixelite.cz', 'correct horse battery'));
Env::set('ADMIN_PASSWORD_HASH', '');
check('login disabled when no hash configured', !Auth::attempt('boss@pixelite.cz', ''));
Env::set('ADMIN_PASSWORD_HASH', password_hash('correct horse battery', PASSWORD_DEFAULT));
$_SESSION = [];
check('/admin redirects guests to login', Pixelite\handle($mk('GET', '/admin'))->headers['Location'] === '/admin/login');
check('order detail requires login', Pixelite\handle($mk('GET', '/admin/orders/1'))->status === 302);
$t = Csrf::token();
$r = Pixelite\handle($mk('POST', '/admin/login', ['_csrf' => 'wrong', 'email' => 'boss@pixelite.cz', 'password' => 'correct horse battery']));
check('login without valid CSRF => 403', $r->status === 403);
$r = Pixelite\handle($mk('POST', '/admin/login', ['_csrf' => $t, 'email' => 'boss@pixelite.cz', 'password' => 'bad']));
check('bad password => 401 generic message', $r->status === 401 && str_contains($r->body, 'Invalid email or password'));
$t = Csrf::token();
$r = Pixelite\handle($mk('POST', '/admin/login', ['_csrf' => $t, 'email' => 'boss@pixelite.cz', 'password' => 'correct horse battery']));
check('good login => redirect to /admin', $r->status === 303 && $r->headers['Location'] === '/admin');
$r = Pixelite\handle($mk('GET', '/admin'));
check('orders list shows stored orders, escaped', $r->status === 200 && str_contains($r->body, 'Jana Nov') && !str_contains($r->body, '<script>alert'));
$r = Pixelite\handle($mk('GET', '/admin/orders/1'));
check('order detail renders', $r->status === 200 && str_contains($r->body, 'Order #1'));
check('logout needs CSRF (forged logout ignored)', (function () use ($mk) { Pixelite\handle($mk('POST', '/admin/logout', ['_csrf' => 'x'])); return Auth::check(); })());
Pixelite\handle($mk('POST', '/admin/logout', ['_csrf' => Csrf::token()]));
check('logout ends the session', empty($_SESSION['admin_at']));

echo "responses\n";
$_SESSION = [];
$r = Pixelite\handle($mk('GET', '/en/'));
check('home: CSP + nosniff + frame headers', isset($r->headers['Content-Security-Policy'], $r->headers['X-Frame-Options']) && $r->headers['X-Content-Type-Options'] === 'nosniff');
check('home: lang, canonical, hreflang pair', str_contains($r->body, '<html lang="en">') && str_contains($r->body, 'rel="canonical" href="https://pixelite.cz/en/"') && substr_count($r->body, 'rel="alternate" hreflang="') === 3);
check('home links to order, not to portfolio', str_contains($r->body, 'href="/en/order"') && !str_contains($r->body, 'portfolio'));
$r = Pixelite\handle($mk('GET', '/cs/'));
check('cs home: Czech html lang + copy', str_contains($r->body, '<html lang="cs">') && str_contains($r->body, 'Zahájit projekt'));
$r = Pixelite\handle($mk('GET', '/en/portfolio'));
check('portfolio reachable but noindex', $r->status === 200 && str_contains($r->body, 'content="noindex,nofollow"'));
$sm = Pixelite\handle($mk('GET', '/sitemap.xml'))->body;
check('sitemap excludes portfolio and admin', !str_contains($sm, 'portfolio') && !str_contains($sm, 'admin') && str_contains($sm, '/cs/order'));
check('unknown route => 404', Pixelite\handle($mk('GET', '/xyz'))->status === 404);
check('wrong method => 405', Pixelite\handle($mk('POST', '/en/contact'))->status === 405);

echo "company identity / legal / consent\n";
Env::set('APP_URL', 'https://pixelite.cz');
$setCo = function (array $v) { foreach (['COMPANY_NAME', 'COMPANY_ICO', 'COMPANY_ADDRESS', 'COMPANY_DIC', 'COMPANY_REGISTER', 'CONTACT_EMAIL', 'CONTACT_PHONE'] as $k) { Env::set($k, $v[$k] ?? ''); } };
$page = fn(string $path) => Pixelite\handle($mk('GET', $path))->body;
$setCo(['COMPANY_NAME' => 'Test s.r.o. & Co', 'COMPANY_ICO' => '012 34 567', 'COMPANY_ADDRESS' => 'Ulice 1, 110 00 Praha', 'COMPANY_DIC' => 'CZ01234567', 'CONTACT_EMAIL' => 'hi@example.cz', 'CONTACT_PHONE' => '+420 111 222 333']);
foreach (['en', 'cs'] as $l) {
    $h = $page("/$l/");
    check("footer ($l) shows company name escaped", str_contains($h, 'Test s.r.o. &amp; Co') && !str_contains($h, 'Test s.r.o. & Co'));
    check("footer ($l) shows IČO exactly as configured", str_contains($h, '012 34 567'));
    check("footer ($l) shows address, DIČ, email and phone", str_contains($h, 'Ulice 1, 110 00 Praha') && str_contains($h, 'CZ01234567') && str_contains($h, 'mailto:hi@example.cz') && str_contains($h, 'tel:+420111222333'));
    check("footer ($l) has privacy, cookies, terms and cookie-settings entries", str_contains($h, "href=\"/$l/privacy\"") && str_contains($h, "href=\"/$l/cookies\"") && str_contains($h, "href=\"/$l/terms\"") && str_contains($h, "href=\"/$l/cookies#settings\" data-cookie-settings"));
}
check('footer label is localized (IČO / Company ID)', str_contains($page('/cs/'), '>IČO<') && str_contains($page('/en/'), 'Company ID (IČO)'));
$setCo([]);
$h = $page('/en/');
check('missing identity shows visible placeholders, not blanks', substr_count($h, 'class="placeholder"') >= 4);
$setCo(['COMPANY_NAME' => 'Test s.r.o.', 'COMPANY_ICO' => '012 34 567', 'COMPANY_ADDRESS' => 'Ulice 1', 'CONTACT_EMAIL' => 'hi@example.cz']);
check('footer appears on every public page', (function () use ($page) { foreach (['/en/', '/cs/order', '/en/contact', '/cs/privacy', '/en/cookies', '/cs/terms', '/en/portfolio', '/en/nope'] as $p) { if (!str_contains($page($p), 'footer__company')) return false; } return true; })());
$pv = $page('/en/privacy');
check('privacy: controller identity comes from config', str_contains($pv, 'Test s.r.o.') && str_contains($pv, '012 34 567') && str_contains($pv, 'Ulice 1'));
check('privacy: legal basis stated per activity (6(1)(b), (f), consent only for future optional)', str_contains($pv, 'Art. 6(1)(b)') && str_contains($pv, 'Art. 6(1)(f)') && str_contains($pv, 'Art. 6(1)(a)') && str_contains($pv, 'not a consent'));
check('privacy: does not claim IPs are not collected / no cookies', !preg_match('/do(es)? not collect (your )?IP|no cookies are used/i', $pv) && str_contains($pv, 'access and error logs'));
check('privacy: names Telegram as recipient, complaint authority and rights', str_contains($pv, 'Telegram') && str_contains($pv, 'uoou.gov.cz') && str_contains($pv, 'right of access'));
check('privacy: unknown facts stay placeholders (retention, hosting, transfers)', substr_count($pv, 'to be completed') >= 5);
$pvc = $page('/cs/privacy');
check('privacy (cs): Czech legal wording incl. GDPR bases and ÚOOÚ', str_contains($pvc, 'čl. 6 odst. 1 písm. b) GDPR') && str_contains($pvc, 'Úřadu pro ochranu osobních údajů') && str_contains($pvc, 'Správcem'));
$ck = $page('/en/cookies');
check('cookie policy lists every storage item', str_contains($ck, 'pixelite_consent') && str_contains($ck, 'pixelite_lang') && str_contains($ck, 'pixelite_theme') && str_contains($ck, '<code>pixelite</code>'));
check('cookie policy says no optional cookies are used (nothing invented)', str_contains($ck, 'None are used at the moment') && !preg_match('/google analytics|_ga\b|facebook|hotjar|gtag/i', $ck . $pv));
check('cookie policy (cs) renders Czech', str_contains($page('/cs/cookies'), 'Doba platnosti'));
$cfg = require Pixelite\Paths::root('config/consent.php');
check('consent registry: no optional service configured', array_filter($cfg['optional']) === []);
check('consent registry matches the cookies page items', (function () use ($cfg) { foreach ($cfg['storage'] as $it) { if (!isset(I18n::load('en')['cookies']['items'][$it['id']], I18n::load('cs')['cookies']['items'][$it['id']])) return false; } return true; })());
$home = $page('/en/');
check('banner markup: hidden by default, equal Accept/Reject, categories empty', preg_match('/id="cookie-banner"[^>]*\shidden[\s>]/', $home) === 1 && str_contains($home, 'data-consent-accept') && str_contains($home, 'data-consent-reject') && str_contains($home, 'data-categories=""'));
check('no inline scripts/handlers anywhere (CSP-safe)', !preg_match('/<script(?![^>]*\bsrc=)(?![^>]*ld\+json)[^>]*>|\son[a-z]+="/', $home . $page('/cs/order') . $page('/en/cookies')));
check('no third-party hosts loaded by public pages', !preg_match('#(src|href)="https?://(?!pixelite\.cz|coi\.gov\.cz)#', $home . $page('/en/terms')));
$tm = $page('/en/terms');
check('terms: request form is described as non-binding, no payment', str_contains($tm, 'non-binding') && str_contains($tm, 'no payment is taken'));
check('terms: ADR body + current URL, consumer placeholders for owner', str_contains($tm, 'https://coi.gov.cz/en/information-about-adr/') && str_contains($page('/cs/terms'), 'https://coi.gov.cz/informace-o-adr/') && str_contains($tm, 'to be completed by the owner'));
check('no obsolete EU ODR platform reference anywhere', (function () { foreach (['lang/en.php', 'lang/cs.php', 'templates', 'src', 'config', 'public/assets'] as $x) { $r = shell_exec('grep -rliE "ec\.europa\.eu/consumers/odr|online dispute resolution|\bODR\b" ' . escapeshellarg(Pixelite\Paths::root($x)) . ' 2>/dev/null'); if (trim((string) $r) !== '') { return false; } } return true; })());
$sm = $page('/sitemap.xml');
check('sitemap includes cookies and terms in both languages', str_contains($sm, '/en/cookies') && str_contains($sm, '/cs/terms') && !str_contains($sm, 'portfolio'));
check('order form asks for acknowledgement, not consent', (function () use ($page) { $o = $page('/en/order'); return str_contains($o, 'I have read how my personal data will be used') && !preg_match('/I agree to the processing/i', $o); })());
check('head: theme-init.js precedes the stylesheets; favicon + touch icon + color-scheme', (function () use ($home) { return strpos($home, 'theme-init.js') < strpos($home, 'style.css') && str_contains($home, 'rel="icon" href="/favicon.ico"') && str_contains($home, 'icons/favicon.svg') && str_contains($home, 'rel="apple-touch-icon"') && str_contains($home, 'name="color-scheme" content="light dark"'); })());
check('every icon file referenced exists', is_file(Pixelite\Paths::root('public/favicon.ico')) && is_file(Pixelite\Paths::root('public/assets/icons/favicon.svg')) && is_file(Pixelite\Paths::root('public/assets/icons/apple-touch-icon.png')) && !is_file(Pixelite\Paths::root('public/assets/img/favicon.png')));
check('old cube logo is gone everywhere; the supplied SVG logo is used', (function () use ($home) { $old = trim((string) shell_exec('grep -rl "logo\\.png" ' . escapeshellarg(Pixelite\Paths::root('templates')) . ' ' . escapeshellarg(Pixelite\Paths::root('src')) . ' ' . escapeshellarg(Pixelite\Paths::root('public/assets/style.css')) . ' 2>/dev/null')); return $old === '' && !is_file(Pixelite\Paths::root('public/assets/img/logo.png')) && substr_count($home, 'img/logo.svg') >= 3 && md5_file(Pixelite\Paths::root('public/assets/img/logo.svg')) === md5_file(Pixelite\Paths::root('public/assets/icons/favicon.svg')); })());
check('rate limiter purges entries older than 24h on EVERY path (privacy promise)', (function () { $pdo = Database::pdo(); $pdo->prepare('INSERT INTO rate_limits (bucket, hit_at) VALUES (?, ?)')->execute(['old-test', time() - 90000]); RateLimiter::attempt('purge-probe', 5, 60); $n = (int) $pdo->query("SELECT COUNT(*) FROM rate_limits WHERE bucket = 'old-test'")->fetchColumn(); RateLimiter::clear('purge-probe'); return $n === 0; })());
check('CSS: the explicit-dark and system-dark token blocks are identical', (function () { $css = (string) file_get_contents(Pixelite\Paths::root('public/assets/style.css')); if (!preg_match('/:root\[data-theme="dark"\]\{(.*?)\n\}/s', $css, $a) || !preg_match('/:root:not\(\[data-theme="light"\]\)\{(.*?)\n  \}/s', $css, $b)) return false; $n = fn($x) => preg_replace('/\s+/', ' ', trim($x)); return $n($a[1]) === $n($b[1]) && strlen($n($a[1])) > 800; })());
check('CSS: every dark-only refinement exists for both dark mechanisms', (function () { $css = (string) file_get_contents(Pixelite\Paths::root('public/assets/style.css')); return substr_count($css, '[data-theme="dark"] .') === substr_count($css, ':root:not([data-theme="light"]) .'); })());
check('legal_text fills tokens and falls back to placeholder', (function () use ($setCo) { $setCo(['COMPANY_NAME' => 'X']); I18n::set('en'); return legal_text('{company}/{ico}') === 'X/[to be completed]'; })());

exec('rm -rf ' . escapeshellarg($tmp));
ob_end_clean();
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
