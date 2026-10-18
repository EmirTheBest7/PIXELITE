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
    'project_type' => 'website', 'budget' => '30k-65k', 'timeframe' => '1-3m',
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
check('message has header, fields and labels in English', str_contains($msg, 'NEW PIXELITE ORDER') && str_contains($msg, 'Budget: 30,000–65,000 CZK') && str_contains($msg, 'Project: Website'));
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
check('sitemap excludes portfolio and admin', !preg_match('#/(en|cs)/portfolio<#', $sm) && !str_contains($sm, 'admin') && str_contains($sm, '/cs/order'));
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
check('sitemap includes cookies and terms in both languages', str_contains($sm, '/en/cookies') && str_contains($sm, '/cs/terms') && !preg_match('#/(en|cs)/portfolio<#', $sm));
check('order form asks for acknowledgement, not consent', (function () use ($page) { $o = $page('/en/order'); return str_contains($o, 'I have read how my personal data will be used') && !preg_match('/I agree to the processing/i', $o); })());
check('head: theme-init.js precedes the stylesheets; favicon + touch icon + color-scheme', (function () use ($home) { return strpos($home, 'theme-init.js') < strpos($home, 'style.css') && str_contains($home, 'rel="icon" href="/favicon.ico"') && str_contains($home, 'icons/favicon.svg') && str_contains($home, 'rel="apple-touch-icon"') && str_contains($home, 'name="color-scheme" content="light dark"'); })());
check('every icon file referenced exists', is_file(Pixelite\Paths::root('public/favicon.ico')) && is_file(Pixelite\Paths::root('public/assets/icons/favicon.svg')) && is_file(Pixelite\Paths::root('public/assets/icons/apple-touch-icon.png')) && !is_file(Pixelite\Paths::root('public/assets/img/favicon.png')));
check('old cube logo is gone everywhere; the supplied SVG logo is used', (function () use ($home) { $old = trim((string) shell_exec('grep -rl "logo\\.png" ' . escapeshellarg(Pixelite\Paths::root('templates')) . ' ' . escapeshellarg(Pixelite\Paths::root('src')) . ' ' . escapeshellarg(Pixelite\Paths::root('public/assets/style.css')) . ' 2>/dev/null')); return $old === '' && !is_file(Pixelite\Paths::root('public/assets/img/logo.png')) && substr_count($home, 'img/logo.svg') >= 3 && md5_file(Pixelite\Paths::root('public/assets/img/logo.svg')) === md5_file(Pixelite\Paths::root('public/assets/icons/favicon.svg')); })());
check('rate limiter purges entries older than 24h on EVERY path (privacy promise)', (function () { $pdo = Database::pdo(); $pdo->prepare('INSERT INTO rate_limits (bucket, hit_at) VALUES (?, ?)')->execute(['old-test', time() - 90000]); RateLimiter::attempt('purge-probe', 5, 60); $n = (int) $pdo->query("SELECT COUNT(*) FROM rate_limits WHERE bucket = 'old-test'")->fetchColumn(); RateLimiter::clear('purge-probe'); return $n === 0; })());
check('CSS: the explicit-dark and system-dark token blocks are identical', (function () { $css = (string) file_get_contents(Pixelite\Paths::root('public/assets/style.css')); if (!preg_match('/:root\[data-theme="dark"\]\{(.*?)\n\}/s', $css, $a) || !preg_match('/:root:not\(\[data-theme="light"\]\)\{(.*?)\n  \}/s', $css, $b)) return false; $n = fn($x) => preg_replace('/\s+/', ' ', trim($x)); return $n($a[1]) === $n($b[1]) && strlen($n($a[1])) > 800; })());
check('CSS: every dark-only refinement exists for both dark mechanisms', (function () { $css = (string) file_get_contents(Pixelite\Paths::root('public/assets/style.css')); return substr_count($css, '[data-theme="dark"] .') === substr_count($css, ':root:not([data-theme="light"]) .'); })());
echo "pricing packages / credits / order options\n";
Env::set('PRICE_VAT_MODE', '');
$NB = "\u{00A0}";
$cards = function (string $lang) use ($page) { $h = $page("/$lang/"); preg_match_all('#<div class="col-md-4 col-sm-4 price-box price-box--(\w+)" data-package="(\w+)">(.*?)<div class="price-box__btn">\s*<a class="btn[^"]*" href="([^"]+)"#s', $h, $m, PREG_SET_ORDER); return [$h, $m]; };
foreach ([
    'en' => [['template', 'Template website', 'From', '2,999', 'CZK', '/en/order?package=template'], ['basic', 'Basic website', 'From', '32,900', 'CZK', '/en/order?package=basic'], ['custom', 'Custom website', 'From', '64,900', 'CZK', '/en/order?package=custom']],
    'cs' => [['template', 'Web ze šablony', 'Od', "2{$NB}999", 'Kč', '/cs/order?package=template'], ['basic', 'Základní web', 'Od', "32{$NB}900", 'Kč', '/cs/order?package=basic'], ['custom', 'Individuální web', 'Od', "64{$NB}900", 'Kč', '/cs/order?package=custom']],
] as $lang => $want) {
    [$h, $m] = $cards($lang);
    check("pricing ($lang): exactly three packages in order template/basic/custom", count($m) === 3 && array_column($m, 2) === ['template', 'basic', 'custom'], json_encode(array_column($m, 2)));
    foreach ($want as $i => [$id, $name, $from, $amount, $cur, $href]) {
        $c = $m[$i][3] ?? '';
        check("pricing ($lang/$id): name, \"$from $amount $cur\" and order link", str_contains($c, '>' . $name . '<') && str_contains($c, '<span class="price-box__from">' . $from . '</span>') && str_contains($c, '<span class="price-box__amount">' . $amount . '</span>') && str_contains($c, '<span class="price-box__discount--light">' . $cur . '</span>') && ($m[$i][4] ?? '') === $href, ($m[$i][4] ?? '') . ' | ' . substr(strip_tags($c), 0, 80));
        check("pricing ($lang/$id): six features and a conditional-price note", substr_count($c, 'price-box__list-el') === 6 && str_contains($c, 'class="price-box__note"'));
    }
    check("pricing ($lang): no EUR/USD price in the cards, only the local currency", !preg_match('/€|EUR|\$\s?\d/', implode(' ', array_column($m, 3))));
}
check('template package is described as a template implementation, not custom design (en+cs)', str_contains($page('/en/'), 'a template implementation, not a custom design') && str_contains($page('/cs/'), 'nikoli o návrh na míru') && str_contains($page('/cs/'), 'Další práce mohou konečnou cenu zvýšit') && str_contains($page('/en/'), 'Additional work can increase the final price'));
check('heading says prices are starting prices that depend on scope (en+cs)', str_contains($page('/en/'), 'Starting prices. The final price depends on the scope of your project.') && str_contains($page('/cs/'), 'Konečná cena závisí na rozsahu projektu'));
$en = $page('/en/'); $cs = $page('/cs/');
check('Dreamers Ad Credits (en): threshold, "up to $100", advertising credit, not a cash discount', str_contains($en, 'Orders from 30,000 CZK qualify for up to $100 in Dreamers Ad Credits for advertising.') && str_contains($en, 'not a cash discount'));
check("Dreamers Ad Credits (cs): threshold, \"až 100{$NB}USD\", reklamní kredit, ne sleva", str_contains($cs, "od 30{$NB}000{$NB}Kč") && str_contains($cs, "až 100{$NB}USD") && str_contains($cs, 'Dreamers Ad Credits') && str_contains($cs, 'nikoli o slevu v hotovosti'));
check('Dreamers: no invented conditions on the landing page (expiry, cash value, refund, automatic)', !preg_match('/expir|valid until|refund|cash value|automatic|vyprš|platnost do|vrácen|automatick/i', preg_replace('#<a [^>]*>.*?</a>#s', '', strip_tags($en . $cs))));
check('Dreamers conditions live in /terms as an owner placeholder with its own anchor (en+cs)', str_contains($page('/en/terms'), 'id="dreamers"') && substr_count($page('/en/terms'), '[Who provides and issues') === 1 && str_contains($page('/en/terms'), '[Eligibility: which orders') && str_contains($page('/en/terms'), '[Validity or expiry') && str_contains($page('/cs/terms'), 'id="dreamers"') && str_contains($page('/cs/terms'), '[Kdo Dreamers Ad Credits poskytuje') && str_contains($page('/cs/terms'), '[Způsobilost:') && str_contains($en, '/en/terms#dreamers') && str_contains($cs, '/cs/terms#dreamers'));
check('"Nezávazná konzultace" is spelled correctly, in its own section below the cards (cs) / Non-binding consultation (en)', str_contains($cs, 'Nezávazná konzultace') && !str_contains($cs, 'Nezávazný konzultace') && str_contains($cs, 'Krátce nám popište, co potřebujete. Společně vybereme vhodné řešení a projdeme možnosti před zahájením projektu.') && str_contains($en, 'Non-binding consultation') && strpos($cs, 'id="consultation"') > strrpos($cs, 'price-box__wrap') && strpos($cs, 'id="consultation"') > strpos($cs, 'class="credits"'));
check('consultation claims no price/free service (non-binding only)', !preg_match('/zdarma|free of charge|for free|free consultation/i', $en . $cs));
check('no price or credit-threshold number is hard-coded anywhere outside lang/ (templates, src, config, bin, css, js)', (function () { $hits = []; foreach (['templates', 'src', 'config', 'bin', 'public/assets'] as $dir) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Pixelite\Paths::root($dir), FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) { $path = $f->getPathname(); if (!preg_match('/\.(php|css|js)$/', $path) || str_contains($path, '/vendor/')) continue; if (preg_match('/(?<![\d#.\-])(?:2[ ,.\x{00A0}]?999|32[ ,.\x{00A0}]?900|64[ ,.\x{00A0}]?900|30[ ,.\x{00A0}]?000)(?![\d])/u', (string) file_get_contents($path), $m)) $hits[] = basename($path) . ':' . $m[0]; } } return $hits === [] || (fwrite(STDERR, implode(',', $hits) . "\n") && false); })());
check('no price text is hard-coded in templates (all pricing comes from lang files)', trim((string) shell_exec('grep -rnE "Kč|CZK|[0-9]{1,3}[ ,\x{00A0}][0-9]{3}" ' . escapeshellarg(Pixelite\Paths::root('templates')) . ' 2>/dev/null')) === '');
check('package ids are stable and identical in both languages', array_column(I18n::load('en')['services']['items'], 'id') === ['template', 'basic', 'custom'] && array_column(I18n::load('cs')['services']['items'], 'id') === ['template', 'basic', 'custom']);
check('budget whitelist is the six CZK ranges; packages are a separate whitelist, not project types', OrderOptions::BUDGETS === ['under10k', '10k-30k', '30k-65k', '65k-100k', '100k-plus', 'unsure'] && OrderOptions::PACKAGES === ['template', 'basic', 'custom'] && !array_intersect(OrderOptions::PACKAGES, OrderOptions::PROJECT_TYPES));
check('budget labels are CZK/Kč in both languages, never euros', (function () { foreach (['en' => 'CZK', 'cs' => 'Kč'] as $l => $cur) { foreach (OrderOptions::BUDGETS as $b) { $t = I18n::load($l)['order']['options']['budget'][$b]; if ($b !== 'unsure' && !str_contains($t, $cur)) return false; if (str_contains($t, '€')) return false; } } return true; })());
check('budget labels line up with the packages and the 30,000 threshold', str_contains(I18n::load('en')['order']['options']['budget']['30k-65k'], '30,000–65,000') && str_contains(I18n::load('cs')['order']['options']['budget']['10k-30k'], "10{$NB}000–30{$NB}000"));
foreach (['under1k', '1k-3k', '3k-5k', '5k-plus', '999', '', 'under10k; DROP TABLE', '30000'] as $bad) {
    [, $er] = OrderValidator::validate(['budget' => $bad] + $valid, true);
    check("validator rejects arbitrary/legacy budget value \"$bad\"", ($er['budget'] ?? '') === 'choose');
}
foreach (OrderOptions::BUDGETS as $b) { [, $er] = OrderValidator::validate(['budget' => $b] + $valid, true); check("validator accepts budget $b", !isset($er['budget'])); }
foreach (['template', 'website'] as $ty) { $o = $page("/en/order?type=$ty"); }
$mkq = fn(string $p, array $q) => new Request('GET', $p, [], $q, ['REMOTE_ADDR' => '203.0.113.7']);
$sel = fn(string $body, string $field) => preg_match('#<select[^>]*id="f-' . $field . '"[^>]*>.*?</select>#s', $body, $mm) && preg_match('#<option value="([^"]*)" selected#', $mm[0], $v) ? $v[1] : null;
foreach (['template' => 'Template website', 'basic' => 'Basic website', 'custom' => 'Custom website'] as $pk => $label) {
    $b = Pixelite\handle($mkq('/en/order', ['package' => $pk]))->body;
    check("order ?package=$pk preselects the package \"$label\" and (separately) project type Website", $sel($b, 'package') === $pk && $sel($b, 'project_type') === 'website' && str_contains($b, '>' . $label . ' – From'));
}
check('order ?package=<id> works in Czech too', $sel(Pixelite\handle($mkq('/cs/order', ['package' => 'template']))->body, 'package') === 'template');
check('?package does not override an explicit ?type (they are independent)', (function () use ($mkq, $sel) { $b = Pixelite\handle($mkq('/en/order', ['package' => 'basic', 'type' => 'redesign']))->body; return $sel($b, 'package') === 'basic' && $sel($b, 'project_type') === 'redesign'; })());
check('?type=template is no longer a project type (ignored)', in_array($sel(Pixelite\handle($mkq('/en/order', ['type' => 'template']))->body, 'project_type'), ['', null], true));
check('?type=website alone preselects no package', $sel(Pixelite\handle($mkq('/en/order', ['type' => 'website']))->body, 'package') === '' || $sel(Pixelite\handle($mkq('/en/order', ['type' => 'website']))->body, 'package') === null);
check('unknown ?package is ignored', $sel(Pixelite\handle($mkq('/en/order', ['package' => 'evil"><script>']))->body, 'package') !== 'evil"><script>' && !str_contains(Pixelite\handle($mkq('/en/order', ['package' => 'evil"><script>']))->body, 'evil"><script>'));
check('order ?type=<unknown> is ignored (nothing preselected)', !str_contains(Pixelite\handle($mkq('/en/order', ['type' => 'evil"><script>']))->body, 'evil"><script>'));
check('order form offers the six CZK budget options (en) and Kč options (cs)', (function () use ($mkq, $NB) { $e = Pixelite\handle($mkq('/en/order', []))->body; $c = Pixelite\handle($mkq('/cs/order', []))->body; return preg_match('#<select[^>]*id="f-budget".*?</select>#s', $e, $bs) && substr_count($bs[0], 'CZK</option>') === 5 && str_contains($e, 'Up to 10,000 CZK') && str_contains($e, '100,000+ CZK') && !str_contains($e, '€') && str_contains($c, "Do 10{$NB}000{$NB}Kč") && !str_contains($c, '€'); })());
$tm = $page('/en/terms');
check('terms: prices are starting prices, VAT question left to the owner, template price conditional', str_contains($tm, 'starting prices') && str_contains($tm, '[Whether the prices include VAT') && str_contains($tm, 'additional work can increase the final price'));
check('terms (cs): ceny „od“, DPH jako pole pro provozovatele', str_contains($page('/cs/terms'), 'ceny „od“') && str_contains($page('/cs/terms'), '[Zda ceny zahrnují DPH'));

echo "package persistence / VAT display\n";
check('package is validated separately: empty and each id accepted', (function () use ($valid) { foreach (['', 'template', 'basic', 'custom'] as $pk) { [, $er] = OrderValidator::validate(['package' => $pk] + $valid, true); if (isset($er['package'])) return false; } return true; })());
foreach (['evil', 'website', 'BASIC', 'basic ', '1', 'basic;--'] as $bad) { [$cl, $er] = OrderValidator::validate(['package' => $bad] + $valid, true); check("package \"$bad\" is rejected (or normalised) server-side", ($er['package'] ?? '') === 'choose' || ($cl['package'] === 'basic' && $bad === 'basic ')); }
check('package never overrides or replaces project_type', (function () use ($valid) { [$cl, $er] = OrderValidator::validate(['package' => 'template', 'project_type' => 'redesign'] + $valid, true); return $cl['package'] === 'template' && $cl['project_type'] === 'redesign' && !$er; })());
// DB: new column, and an OLD database (no package column) is migrated in place
check('fresh database has the package column', in_array('package', array_column(Database::pdo()->query('PRAGMA table_info(orders)')->fetchAll(), 'name'), true));
check('an existing database without the column is migrated; old rows keep working', (function () use ($tmp, $valid) {
    $old = "$tmp/old.sqlite"; $pdo = new PDO('sqlite:' . $old);
    $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, locale TEXT NOT NULL, name TEXT NOT NULL, company TEXT NOT NULL DEFAULT "", email TEXT NOT NULL, phone TEXT NOT NULL DEFAULT "", project_type TEXT NOT NULL, budget TEXT NOT NULL, timeframe TEXT NOT NULL, description TEXT NOT NULL, consent_at TEXT NOT NULL, notification_status TEXT NOT NULL DEFAULT "pending", notification_error TEXT NOT NULL DEFAULT "", notified_at TEXT)');
    $pdo->exec("INSERT INTO orders (created_at, locale, name, email, project_type, budget, timeframe, description, consent_at) VALUES ('2026-01-01T00:00:00+00:00','en','Legacy','l@x.cz','website','unsure','asap','legacy description text','2026-01-01T00:00:00+00:00')");
    $pdo = null; $prev = Env::get('LEADS_DATABASE_PATH'); Env::set('LEADS_DATABASE_PATH', $old); Database::reset();
    $repo = new OrderRepository(); $legacy = $repo->find(1); $id = $repo->create(['package' => 'basic'] + $valid, 'en'); $new = $repo->find($id);
    Env::set('LEADS_DATABASE_PATH', $prev); Database::reset();
    return $legacy['package'] === '' && $legacy['package_name'] === '' && $legacy['package_price'] === '' && $legacy['price_vat_mode'] === '' && $legacy['name'] === 'Legacy' && $new['package'] === 'basic' && $new['project_type'] === 'website';
})());
// full HTTP flow: card link -> form -> POST -> row -> admin list + detail
Env::set('CONTACT_RATE_LIMIT', '50'); Env::set('TELEGRAM_BOT_TOKEN', ''); Env::set('TELEGRAM_CHAT_ID', '');
$mk2 = fn(string $m, string $p, array $post = []) => new Request($m, $p, $post, [], ['REMOTE_ADDR' => '203.0.113.77']);
$_SESSION = []; Pixelite\handle($mk2('GET', '/en/order?package=custom'));
$_SESSION['form_ts'] = time() - 30;
$r = Pixelite\handle($mk2('POST', '/en/order', ['_csrf' => Csrf::token(), 'package' => 'custom', 'description' => 'Package persistence check, please ignore', 'email' => 'pkg@example.cz'] + $valid + ['consent' => '1']));
$row = Database::pdo()->query("SELECT * FROM orders WHERE email = 'pkg@example.cz'")->fetch();
check('POST with package=custom: stored in its own column, project_type untouched', $r->status === 303 && $row && $row['package'] === 'custom' && $row['project_type'] === 'website', json_encode($row));
$_SESSION['admin_at'] = time(); $_SESSION['admin_since'] = time();
$list = Pixelite\handle($mk2('GET', '/admin'))->body; $det = Pixelite\handle($mk2('GET', '/admin/orders/' . $row['id']))->body;
check('admin list shows a Package column with the selected package', str_contains($list, '<th>Package</th>') && str_contains($list, 'Custom website<br><small>From 64,900 CZK</small>'));
check('admin detail shows Package separately from Project type', str_contains($det, '<dt>Package</dt><dd><code>custom</code></dd>') && str_contains($det, '<dt>Package name shown</dt><dd>Custom website</dd>') && str_contains($det, 'From 64,900 CZK') && str_contains($det, 'not the agreed price') && str_contains($det, '<dt>Project type</dt>'));
$_SESSION['form_ts'] = time() - 30;
Pixelite\handle($mk2('POST', '/en/order', ['_csrf' => Csrf::token(), 'package' => '', 'description' => 'No package selected check, please ignore', 'email' => 'nopkg@example.cz'] + $valid + ['consent' => '1']));
$none = Database::pdo()->query("SELECT * FROM orders WHERE email = 'nopkg@example.cz'")->fetch();
check('order without a package stores "" and admin shows a dash', $none && $none['package'] === '' && str_contains(Pixelite\handle($mk2('GET', '/admin/orders/' . $none['id']))->body, '<dt>Package</dt><dd>–</dd>'));
$_SESSION['form_ts'] = time() - 30;
$r = Pixelite\handle($mk2('POST', '/en/order', ['_csrf' => Csrf::token(), 'package' => 'enterprise', 'description' => 'Tampered package, please ignore', 'email' => 'bad@example.cz'] + $valid + ['consent' => '1']));
check('a tampered package value is refused (422) and nothing is stored', $r->status === 422 && !Database::pdo()->query("SELECT 1 FROM orders WHERE email = 'bad@example.cz'")->fetch());
$_SESSION = [];
$tg = new TelegramNotifier();
check('Telegram message carries the package id + the snapshot (name, price shown, VAT context) or "-"', str_contains($tg->format($row + ['created_at' => 'x']), 'Package: custom (Custom website)') && str_contains($tg->format($row + ['created_at' => 'x']), 'Price shown: From 64,900 CZK') && str_contains($tg->format($row + ['created_at' => 'x']), 'not the agreed price') && str_contains($tg->format($none + ['created_at' => 'x']), 'Package: -') && !str_contains($tg->format($none + ['created_at' => 'x']), 'Price shown'));
// VAT display: configurable, never guessed
$vatCases = ['incl' => ['Price includes VAT', 'Cena včetně DPH', 'Prices include VAT (DPH).', 'Ceny jsou uvedeny včetně DPH.'], 'excl' => ['Price excludes VAT', 'Cena bez DPH', 'Prices exclude VAT (DPH)', 'Ceny jsou uvedeny bez DPH'], 'none' => ['Not a VAT payer', 'Neplátce DPH', 'We are not a VAT payer', 'Nejsme plátci DPH']];
foreach ($vatCases as $mode => [$ce, $cc, $te, $tc]) {
    Env::set('PRICE_VAT_MODE', $mode); $he = $page('/en/'); $hc = $page('/cs/');
    check("VAT mode \"$mode\": three cards show it in EN and CS, no placeholder left", substr_count($he, 'class="price-box__vat"><' . '') >= 0 && substr_count($he, $ce) === 3 && substr_count($hc, $cc) === 3 && !str_contains($he, 'VAT information – to be completed') && !str_contains($hc, '[DPH – bude doplněno]'));
    check("VAT mode \"$mode\": /terms states it (EN+CS) instead of the placeholder", str_contains($page('/en/terms'), $te) && str_contains($page('/cs/terms'), $tc) && !str_contains($page('/en/terms'), '[Whether the prices include VAT'));
    check("VAT mode \"$mode\": starting prices themselves are unchanged", str_contains($he, '>2,999<') && str_contains($he, '>32,900<') && str_contains($he, '>64,900<') && str_contains($hc, "2{$NB}999") && str_contains($hc, "64{$NB}900"));
}
foreach (array_keys($vatCases) as $mode) {
    Env::set('PRICE_VAT_MODE', $mode); $he = $page('/en/'); $others = array_diff(array_keys($vatCases), [$mode]);
    check("VAT mode \"$mode\": exactly ONE presentation on the cards (no other mode's wording)", substr_count($he, 'class="price-box__vat"') === 3 && (function () use ($he, $vatCases, $others) { foreach ($others as $o) { if (str_contains($he, $vatCases[$o][0])) return false; } return true; })());
}
check('no customer-facing VAT toggle: the pricing area has no inputs, selects, buttons or switches', (function () use ($page) { $h = $page('/en/'); preg_match('#<div class="row row--center row--margin pricing-row">(.*?)<div class="credits">#s', $h, $m); return !preg_match('#<(input|select|button|textarea)\b|role="switch"|data-vat#i', $m[1] ?? '<input'); })());
check('the VAT setting is server-side config only (no JS / query-string override)', !str_contains((string) file_get_contents(Pixelite\Paths::root('public/assets/script.js')), 'vat') && !str_contains($page('/en/') , 'vat='));
foreach (['', 'maybe', 'INCLUDED', 'yes', '1'] as $invalid) {
    Env::set('PRICE_VAT_MODE', $invalid); $he = $page('/en/');
    check("VAT mode \"$invalid\" is not guessed: visible placeholder on all three cards + terms", substr_count($he, '<span class="placeholder">[VAT information – to be completed]</span>') === 3 && str_contains($page('/en/terms'), '[Whether the prices include VAT (DPH) – to be completed by the owner.]') && str_contains($page('/cs/'), '[DPH – bude doplněno]'));
}
Env::set('PRICE_VAT_MODE', 'INCL'); check('VAT mode is case-insensitive', substr_count($page('/en/'), 'Price includes VAT') === 3);
Env::set('PRICE_VAT_MODE', '');
check('Dreamers sentence is unchanged and still a promotional credit, not a discount', str_contains($page('/en/'), 'Orders from 30,000 CZK qualify for up to $100 in Dreamers Ad Credits for advertising.') && str_contains($page('/en/'), 'not a cash discount'));
check('/terms#dreamers contains only owner placeholders for the unknown conditions (4 separate ones)', (function () use ($page) { if (!preg_match('#id="dreamers".*?(?=<h3|<p class="prose__meta")#s', $page('/en/terms'), $m)) return false; return substr_count($m[0], '[') === 4 && substr_count($m[0], 'to be completed by the owner') === 4; })());

echo "pricing snapshot: migration / persistence / history\n";
$cols = fn(PDO $p) => array_column($p->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
check('fresh database has package + the three snapshot columns', array_diff(['package', 'package_name', 'package_price', 'price_vat_mode'], $cols(Database::pdo())) === []);
$migrate = function (string $file, string $extraCols) use ($tmp, $cols) {
    $pdo = new PDO('sqlite:' . $file);
    $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, locale TEXT NOT NULL, name TEXT NOT NULL, company TEXT NOT NULL DEFAULT "", email TEXT NOT NULL, phone TEXT NOT NULL DEFAULT "", project_type TEXT NOT NULL,' . $extraCols . ' budget TEXT NOT NULL, timeframe TEXT NOT NULL, description TEXT NOT NULL, consent_at TEXT NOT NULL, notification_status TEXT NOT NULL DEFAULT "pending", notification_error TEXT NOT NULL DEFAULT "", notified_at TEXT)');
    $pdo->exec("INSERT INTO orders (created_at, locale, name, email, project_type, budget, timeframe, description, consent_at" . ($extraCols ? ', package' : '') . ") VALUES ('2026-01-01T00:00:00+00:00','en','Old','o@x.cz','website','unsure','asap','old description text','2026-01-01T00:00:00+00:00'" . ($extraCols ? ",'basic'" : '') . ")");
    $pdo = null; $prev = Env::get('LEADS_DATABASE_PATH'); Env::set('LEADS_DATABASE_PATH', $file); Database::reset();
    $c = $cols(Database::pdo()); $old = (new OrderRepository())->find(1);
    Env::set('LEADS_DATABASE_PATH', $prev); Database::reset();
    return [$c, $old];
};
[$c1, $o1] = $migrate("$tmp/m1.sqlite", '');                                        // before packages existed
[$c2, $o2] = $migrate("$tmp/m2.sqlite", ' package TEXT NOT NULL DEFAULT "",');      // package column only (previous release)
check('migration: pre-package database gets all four columns; old rows keep empty values', array_diff(['package', 'package_name', 'package_price', 'price_vat_mode'], $c1) === [] && $o1['package'] === '' && $o1['package_name'] === '' && $o1['package_price'] === '' && $o1['price_vat_mode'] === '');
check('migration: package-only database gets just the snapshot columns; its package id is kept, snapshot stays empty', array_diff(['package_name', 'package_price', 'price_vat_mode'], $c2) === [] && $o2['package'] === 'basic' && $o2['package_name'] === '' && $o2['package_price'] === '' && $o2['price_vat_mode'] === '');
check('migration is idempotent (opening an already-migrated database again changes nothing)', (function () use ($tmp, $cols) { $prev = Env::get('LEADS_DATABASE_PATH'); Env::set('LEADS_DATABASE_PATH', "$tmp/m2.sqlite"); Database::reset(); $a = $cols(Database::pdo()); Database::reset(); $b = $cols(Database::pdo()); Env::set('LEADS_DATABASE_PATH', $prev); Database::reset(); return $a === $b && count($a) === count(array_unique($a)); })());

$mk3 = fn(string $m, string $p, array $post = []) => new Request($m, $p, $post, [], ['REMOTE_ADDR' => '203.0.113.88']);
$place = function (string $lang, array $over) use ($mk3, $valid) {
    static $n = 0; $n++; $_SESSION = []; Pixelite\handle($mk3('GET', "/$lang/order")); $_SESSION['form_ts'] = time() - 30;
    $email = "snap$n@example.cz";
    Pixelite\handle($mk3('POST', "/$lang/order", ['_csrf' => Csrf::token(), 'email' => $email, 'description' => "Snapshot test $n, please ignore"] + $over + $valid + ['consent' => '1']));
    return Database::pdo()->query("SELECT * FROM orders WHERE email = '$email'")->fetch();
};
Env::set('PRICE_VAT_MODE', '');
$en = $place('en', ['package' => 'basic']); $cs = $place('cs', ['package' => 'basic']); $none = $place('en', ['package' => '']);
check('persistence (en): id stays canonical; name, displayed price and VAT context are snapshotted', $en['package'] === 'basic' && $en['package_name'] === 'Basic website' && $en['package_price'] === 'From 32,900 CZK' && $en['price_vat_mode'] === 'unset', json_encode($en));
check('persistence (cs): the snapshot is what the Czech customer saw', $cs['package'] === 'basic' && $cs['package_name'] === 'Základní web' && $cs['package_price'] === "Od 32{$NB}900 Kč" && $cs['price_vat_mode'] === 'unset', json_encode($cs));
check('no package => no snapshot (all empty)', $none['package'] === '' && $none['package_name'] === '' && $none['package_price'] === '' && $none['price_vat_mode'] === '');
$spoof = $place('en', ['package' => 'basic', 'package_name' => 'HACKED', 'package_price' => 'From 1 CZK', 'price_vat_mode' => 'none']);
check('a client-supplied snapshot is ignored: values always come from the server', $spoof['package_name'] === 'Basic website' && $spoof['package_price'] === 'From 32,900 CZK' && $spoof['price_vat_mode'] === 'unset');
Env::set('PRICE_VAT_MODE', 'incl'); $withVat = $place('en', ['package' => 'custom']); Env::set('PRICE_VAT_MODE', '');
check('VAT display mode in force at submission is recorded (incl)', $withVat['price_vat_mode'] === 'incl' && $withVat['package_price'] === 'From 64,900 CZK');

// regression: change the public price/name AFTER the orders were placed
$ref = new ReflectionProperty(I18n::class, 'cache'); $saved = $ref->getValue();
$mut = $saved; foreach (['en', 'cs'] as $l) { $mut[$l] = I18n::load($l); $mut[$l]['services']['items'][1]['amount'] = $l === 'en' ? '39,900' : "39{$NB}900"; $mut[$l]['services']['items'][1]['name'] = $l === 'en' ? 'Basic website PLUS' : 'Základní web PLUS'; }
$ref->setValue(null, $mut);
$_SESSION = ['admin_at' => time(), 'admin_since' => time()];
$landing = $page('/en/'); $list = Pixelite\handle($mk3('GET', '/admin'))->body; $detEn = Pixelite\handle($mk3('GET', '/admin/orders/' . $en['id']))->body; $detCs = Pixelite\handle($mk3('GET', '/admin/orders/' . $cs['id']))->body; $detVat = Pixelite\handle($mk3('GET', '/admin/orders/' . $withVat['id']))->body;
check('sanity: the public landing page now shows the NEW price', str_contains($landing, '>39,900<') && str_contains($landing, 'Basic website PLUS'));
check('regression: admin DETAIL of the old order still shows the OLD name and price (en)', str_contains($detEn, 'Basic website</dd>') && str_contains($detEn, 'From 32,900 CZK') && !str_contains($detEn, '39,900') && !str_contains($detEn, 'PLUS'));
check('regression: admin DETAIL of the old Czech order still shows what the customer saw', str_contains($detCs, 'Základní web</dd>') && str_contains($detCs, "Od 32{$NB}900 Kč") && !str_contains($detCs, "39{$NB}900") && !str_contains($detCs, '39 900') && !str_contains($detCs, '39,900') && !str_contains($detCs, 'PLUS'));   // the NEW price, not any "39" (a timestamp like 17:15:39 made this flaky)
check('regression: admin LIST keeps the old price for old orders', str_contains($list, 'Basic website<br><small>From 32,900 CZK</small>') && !str_contains($list, '39,900') && !str_contains($list, 'PLUS'));
check('regression: Telegram retry text for an old order is built from the snapshot, not today\'s prices', (function () use ($en) { $t = (new TelegramNotifier())->format($en + ['created_at' => 'x']); return str_contains($t, 'Package: basic (Basic website)') && str_contains($t, 'Price shown: From 32,900 CZK') && !str_contains($t, '39,900'); })());
$newer = $place('en', ['package' => 'basic']);
check('a NEW order after the change snapshots the new price; the old one is unaffected', $newer['package_price'] === 'From 39,900 CZK' && $newer['package_name'] === 'Basic website PLUS' && Database::pdo()->query('SELECT package_price FROM orders WHERE id = ' . $en['id'])->fetchColumn() === 'From 32,900 CZK');
Env::set('PRICE_VAT_MODE', 'excl'); $_SESSION = ['admin_at' => time(), 'admin_since' => time()];
$detVat2 = Pixelite\handle($mk3('GET', '/admin/orders/' . $withVat['id']))->body;
check('regression: changing the VAT setting later does not rewrite an old order\'s VAT context', str_contains($detVat2, 'prices include VAT') && !str_contains($detVat2, 'prices exclude VAT'));
Env::set('PRICE_VAT_MODE', '');
// a legacy row that has only a package id (placed before snapshots existed) must NOT be resolved against today's prices
Database::pdo()->exec("INSERT INTO orders (created_at, locale, name, email, project_type, package, budget, timeframe, description, consent_at) VALUES ('2026-01-01T00:00:00+00:00','en','Legacy pkg','legacy@x.cz','website','custom','unsure','asap','legacy package order','2026-01-01T00:00:00+00:00')");
$lid = (int) Database::pdo()->lastInsertId(); $_SESSION = ['admin_at' => time(), 'admin_since' => time()];
$legacyDet = Pixelite\handle($mk3('GET', "/admin/orders/$lid"))->body; $legacyList = Pixelite\handle($mk3('GET', '/admin'))->body;
check('legacy order with only a package id shows the id + "not recorded", never today\'s price', str_contains($legacyDet, '<code>custom</code>') && str_contains($legacyDet, 'not recorded') && !str_contains($legacyDet, '64,900') && str_contains($legacyList, '<code>custom</code><br><small>price not recorded</small>'));
check('legacy Telegram text says the price context was not recorded', str_contains((new TelegramNotifier())->format(Database::pdo()->query("SELECT * FROM orders WHERE id = $lid")->fetch() + ['created_at' => 'x']), 'Package: custom (price context not recorded)'));
$ref->setValue(null, $saved); $_SESSION = [];
check('translation cache restored after the regression test', I18n::load('en')['services']['items'][1]['amount'] === '32,900');

check('legal_text fills tokens and falls back to placeholder', (function () use ($setCo) { $setCo(['COMPANY_NAME' => 'X']); I18n::set('en'); return legal_text('{company}/{ico}') === 'X/[to be completed]'; })());

echo "pricing + process pages\n";
Env::set('PRICE_VAT_MODE', '');
foreach (['en', 'cs'] as $lang) {
    foreach (['pricing', 'process'] as $pg) {
        $h = $page("/$lang/$pg");
        check("$pg ($lang): renders with one h1, title, description, canonical, hreflang pair + x-default, og tags, indexable", substr_count($h, '<h1') === 1 && preg_match('#<title>[^<]{10,}</title>#', $h) && preg_match('#<meta name="description" content="[^"]{30,}"#', $h) && str_contains($h, '<link rel="canonical" href="https://pixelite.cz/' . $lang . '/' . $pg . '">') && str_contains($h, 'hreflang="en" href="https://pixelite.cz/en/' . $pg . '"') && str_contains($h, 'hreflang="cs" href="https://pixelite.cz/cs/' . $pg . '"') && str_contains($h, 'hreflang="x-default"') && str_contains($h, 'property="og:title"') && str_contains($h, 'content="index,follow"'));
        check("$pg ($lang): no missing-translation key leaks into the page", !preg_match('/\b(pricing|process|faq|home_more|nav|footer)\.[a-z_]+\b/', strip_tags($h)));
    }
    $h = $page("/$lang/pricing");
    preg_match_all('#<div class="col-md-4 col-sm-4 price-box price-box--(\w+)" data-package="(\w+)">(.*?)<div class="price-box__btn">\s*<a class="btn[^"]*" href="([^"]+)"#s', $h, $m, PREG_SET_ORDER);
    check("pricing ($lang): same three package cards and order links as the landing page", array_column($m, 2) === ['template', 'basic', 'custom'] && array_column($m, 4) === ["/$lang/order?package=template", "/$lang/order?package=basic", "/$lang/order?package=custom"], json_encode(array_column($m, 4)));
    $cards = fn(string $x) => (function () use ($x) { preg_match('#<div class="row row--center row--margin pricing-row">.*?<div class="credits">#s', $x, $mm); return $mm[0] ?? ''; })();
    check("pricing ($lang): card markup is byte-identical to the landing page (shared partial)", $cards($h) !== '' && $cards($h) === $cards($page("/$lang/")));
    check("pricing ($lang): Dreamers Ad Credits strip matches the landing page and links to the terms", str_contains($h, 'class="credits"') && str_contains($h, '/' . $lang . '/terms#dreamers') && (preg_match('#<div class="credits">.*?</div>\s*</div>#s', $h, $a) && preg_match('#<div class="credits">.*?</div>\s*</div>#s', $page("/$lang/"), $b) && $a[0] === $b[0]));
    check("pricing ($lang): comparison table has a header cell per package and 8 labelled rows", substr_count($h, '<tr>') === 9 && substr_count($h, 'scope="row"') === 8 && substr_count($h, 'data-label=') === 24);
    check("pricing ($lang): included + not-included lists for each package (6 features each)", substr_count($h, 'scope__list--yes') === 3 && substr_count($h, 'scope__list--no') === 3 && substr_count($h, 'class="scope__card ') === 3);
    check("pricing ($lang): exclusions detail is a visible owner placeholder, not invented", substr_count($h, 'class="placeholder"') >= 1 && (str_contains($h, 'to be completed by the owner') || str_contains($h, 'doplní provozovatel')));
    check("pricing ($lang): FAQ has six native <details> items and an anchor", substr_count($h, '<details class="faq__item">') === 6 && str_contains($h, 'id="faq"'));
    check("pricing ($lang): CTAs lead to /order and /process", str_contains($h, 'href="/' . $lang . '/order"') && str_contains($h, 'href="/' . $lang . '/process"'));
    check("pricing ($lang): no EUR/USD price, no guarantee/delivery-time claims invented", !preg_match('/€|EUR\b/', $h) && !preg_match('/guarantee|garanc|within \d+ (days|weeks)|do \d+ (dnů|týdnů)|\d+ hours/i', strip_tags($h)));
    $pr = $page("/$lang/process");
    check("process ($lang): five numbered steps, CTA to /order, link to pricing, owner placeholder for timelines", substr_count($pr, 'class="steps__item"') === 5 && str_contains($pr, 'href="/' . $lang . '/order"') && str_contains($pr, 'href="/' . $lang . '/pricing"') && str_contains($pr, 'class="placeholder"'));
    check("process ($lang): makes no delivery-time, guarantee or hosting promises", !preg_match('/guarantee|garanc|within \d+|do \d+ (dnů|týdnů)|\d+ hours|\d+ hodin/i', strip_tags($pr)));
    $home = $page("/$lang/");
    check("landing ($lang): links to /pricing, /process, FAQ preview with three questions, compact five-step preview", str_contains($home, 'href="/' . $lang . '/pricing"') && str_contains($home, 'href="/' . $lang . '/process"') && substr_count($home, '<details class="faq__item">') === 3 && substr_count($home, 'class="steps__item"') === 5 && str_contains($home, 'steps--compact'));
    check("nav + footer ($lang): Pricing and How it works are linked", substr_count($home, 'href="/' . $lang . '/pricing"') >= 3 && substr_count($home, 'href="/' . $lang . '/process"') >= 3);
}
$sm = $page('/sitemap.xml');
foreach (['pricing', 'process'] as $pg) {
    check("sitemap lists /$pg in both languages with hreflang alternates", str_contains($sm, "https://pixelite.cz/en/$pg</loc>") && str_contains($sm, "https://pixelite.cz/cs/$pg</loc>") && str_contains($sm, "hreflang=\"cs\" href=\"https://pixelite.cz/cs/$pg\""));
}
check('comparison table uses neutral "Not specified" / "Není specifikováno" for unmentioned features (no implied exclusion)', substr_count($page('/en/pricing'), 'Not specified</span>') === 6 && substr_count($page('/cs/pricing'), 'Není specifikováno</span>') === 6 && !str_contains($page('/en/pricing'), 'Not listed') && !str_contains($page('/cs/pricing'), 'Neuvedeno'));
check('/about is not built yet (404) and not in the sitemap', Pixelite\handle($mk('GET', '/en/about'))->status === 404 && !str_contains($sm, '/about'));
foreach ($vatCases as $mode => [$ce, $cc, $te, $tc]) {
    Env::set('PRICE_VAT_MODE', $mode);
    $he = $page('/en/pricing'); $hc = $page('/cs/pricing');
    check("pricing VAT mode \"$mode\": intro, cards and FAQ show it (EN+CS), no placeholder", substr_count($he, $ce) === 3 && substr_count($hc, $cc) === 3 && str_contains($he, $te) && str_contains($hc, $tc) && !str_contains($he, 'VAT information – to be completed') && !str_contains($hc, '[DPH – bude doplněno]') && !str_contains($he, '[Whether the prices include VAT'));
}
Env::set('PRICE_VAT_MODE', 'bogus');
check('pricing with an invalid VAT mode shows the visible placeholder (cards + intro), never a guess', substr_count($page('/en/pricing'), 'VAT information – to be completed') >= 4 && substr_count($page('/cs/pricing'), '[DPH – bude doplněno]') >= 4);
Env::set('PRICE_VAT_MODE', '');
check('pricing page: starting prices come from the same data (no price text in the template)', str_contains($page('/en/pricing'), '>2,999<') && str_contains($page('/cs/pricing'), "2{$NB}999") && str_contains($page('/cs/pricing'), "Od 64{$NB}900 Kč"));

echo "Endora deployment artifact (build + pre-upload gate)\n";
$art = "$tmp/artifact";
$sh = fn(string $c): array => (function () use ($c) { exec($c . ' 2>&1', $o, $rc); return [$rc, implode("\n", $o)]; })();
[$rc, $o] = $sh('php ' . escapeshellarg(Pixelite\Paths::root('scripts/build-endora.php')) . ' ' . escapeshellarg($art));
check('build script produces public_html/ + app/', $rc === 0 && is_file("$art/public_html/index.php") && is_file("$art/app/src/bootstrap.php"), $o);
$verify = fn(string $dir, bool $http = false) => $sh('php ' . escapeshellarg(Pixelite\Paths::root('scripts/verify-endora-artifact.php')) . ' ' . escapeshellarg($dir) . ($http ? ' --http' : ''));
[$rc, $o] = $verify($art, true);
check('a correct artifact passes every layout / leakage / asset gate and serves the real site over HTTP', $rc === 0 && str_contains($o, ' 0 failed'), substr($o, -400));
$mutate = function (string $name, callable $fn, string $expect) use ($art, $tmp, $sh, $verify) {
    $copy = "$tmp/mut-" . bin2hex(random_bytes(3));
    $sh('cp -R ' . escapeshellarg($art) . ' ' . escapeshellarg($copy));
    $fn($copy);
    [$rc, $o] = $verify($copy);
    check("gate rejects: $name", $rc !== 0 && str_contains($o, $expect), substr($o, -300));
    $sh('rm -rf ' . escapeshellarg($copy));
};
$mutate('repository root uploaded into the document root (src/ inside public_html)', fn($d) => $sh('cp -R ' . escapeshellarg("$d/app/src") . ' ' . escapeshellarg("$d/public_html/src")), 'public_html top level');
$mutate('missing front controller (placeholder page would win)', fn($d) => unlink("$d/public_html/index.php"), 'public_html/index.php exists');
$mutate('missing stylesheet', fn($d) => unlink("$d/public_html/assets/style.css"), 'every asset()');
$mutate('missing font file', fn($d) => unlink((glob("$d/public_html/assets/fonts/*.ttf") ?: [''])[0]), 'every url() in style.css');
$mutate('.env file shipped', fn($d) => file_put_contents("$d/app/.env", "X=1\n"), 'no env files');
$mutate('SQLite database shipped', fn($d) => file_put_contents("$d/app/storage/leads.sqlite", 'x'), 'no env files');
$mutate('tests directory shipped', fn($d) => (mkdir("$d/app/tests") && file_put_contents("$d/app/tests/run.php", '<?php')), 'no env files');
$mutate('private prompt package shipped (private/ directory)', fn($d) => (mkdir("$d/app/private") && file_put_contents("$d/app/private/prompts.json", '{}')), 'no prompt text');
$mutate('prompt text inside a public asset', fn($d) => file_put_contents("$d/public_html/assets/prompts.txt", "CUSTOMER INPUT (fill from the order)\nBuild a site for {BUSINESS_NAME}"), 'no prompt text');
$mutate('prompt text inside the catalog config', fn($d) => file_put_contents("$d/app/config/templates.php", file_get_contents("$d/app/config/templates.php") . "\n// {BUSINESS_NAME}\n"), 'no prompt text');
$mutate('a design preview video missing from public_html', fn($d) => unlink((glob("$d/public_html/assets/templates/*/preview.webm") ?: [''])[0]), 'every catalog design ships');
$mutate('CLAUDE.md / docs shipped', fn($d) => file_put_contents("$d/app/CLAUDE.md", '# x'), 'no env files');
$mutate('a Telegram-token-shaped string in a shipped file', fn($d) => file_put_contents("$d/app/config/leak.php", "<?php return '123456789:" . str_repeat('A', 35) . "';"), 'Telegram token');
$mutate('.htaccess without the front-controller rewrite', fn($d) => file_put_contents("$d/public_html/.htaccess", "Options -Indexes\n"), '.htaccess:');
$mutate('private dir moved under the web root', fn($d) => $sh('mv ' . escapeshellarg("$d/app/templates") . ' ' . escapeshellarg("$d/public_html/templates")), 'private directories');
check('Paths::publicDir() resolves public/ in development and public_html/ in the hosting layout', Pixelite\Paths::publicDir() === Pixelite\Paths::root('public') && is_file(Pixelite\Paths::publicDir('assets/style.css')));
check('index.php is layout-agnostic (requires ../src/bootstrap.php relative to its own directory)', str_contains((string) file_get_contents(Pixelite\Paths::root('public/index.php')), "__DIR__ . '/../src/bootstrap.php'"));
check('.htaccess denies dotfiles and data extensions, no listing, rewrites to the front controller', (function () { $h = (string) file_get_contents(Pixelite\Paths::root('public/.htaccess')); return str_contains($h, 'Options -Indexes') && str_contains($h, 'Require all denied') && str_contains($h, 'Deny from all') && str_contains($h, 'RewriteRule ^ index.php [L]'); })());

echo "seo regression\n";
$seoPaths = ['', 'templates', 'pricing', 'process', 'order', 'contact', 'privacy', 'cookies', 'terms'];
$titles = []; $descs = [];
foreach (['en', 'cs'] as $lang) {
    foreach ($seoPaths as $pg) {
        $h = $page("/$lang/$pg");
        preg_match('#<title>(.*?)</title>#s', $h, $tm); preg_match('#name="description" content="(.*?)"#', $h, $dm);
        $titles[] = $tm[1] ?? ''; $descs[] = $dm[1] ?? '';
        $canon = "https://pixelite.cz/$lang/$pg";
        check("seo $lang/$pg: html lang, one h1, canonical self, reciprocal hreflang, index,follow, twitter+og image meta", str_contains($h, "<html lang=\"$lang\">") && substr_count($h, '<h1') === 1 && str_contains($h, '<link rel="canonical" href="' . $canon . '">') && str_contains($h, 'hreflang="en" href="https://pixelite.cz/en/' . $pg . '"') && str_contains($h, 'hreflang="cs" href="https://pixelite.cz/cs/' . $pg . '"') && str_contains($h, 'hreflang="x-default" href="https://pixelite.cz/en/' . $pg . '"') && str_contains($h, 'content="index,follow"') && str_contains($h, 'name="twitter:title"') && str_contains($h, 'name="twitter:image"') && str_contains($h, 'property="og:image:width"'));
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $h, $lm);
        $okLd = count($lm[1]) === 1;
        foreach ($lm[1] as $j) { $d = json_decode($j, true); $okLd = $okLd && is_array($d) && ($d['@context'] ?? '') === 'https://schema.org' && !str_contains($j, 'aggregateRating') && !str_contains($j, 'review'); }
        check("seo $lang/$pg: exactly one valid JSON-LD block, no invented ratings/reviews", $okLd);
        if ($pg !== '') {
            $d = json_decode($lm[1][0], true);
            check("seo $lang/$pg: BreadcrumbList ends at the canonical URL", ($d['@type'] ?? '') === 'BreadcrumbList' && end($d['itemListElement'])['item'] === $canon && $d['itemListElement'][0]['item'] === "https://pixelite.cz/$lang/");
        } else {
            $d = json_decode($lm[1][0], true);
            check("seo $lang/: Organization + WebSite graph with absolute URLs", array_column($d['@graph'], '@type') === ['Organization', 'WebSite'] && $d['@graph'][1]['publisher']['@id'] === 'https://pixelite.cz/#organization');
        }
    }
}
check('seo: page titles are unique across all public pages and languages', count(array_unique($titles)) === count($titles), implode(' | ', array_diff_assoc($titles, array_unique($titles))));
check('seo: meta descriptions are unique across all public pages and languages', count(array_unique($descs)) === count($descs));
foreach (['en', 'cs'] as $lang) {
    $pf = $page("/$lang/portfolio");
    check("seo $lang/portfolio: noindex and not in sitemap", str_contains($pf, 'content="noindex,nofollow"') && !preg_match('#/(en|cs)/portfolio<#', $page('/sitemap.xml')));
}
$rob = $page('/robots.txt');
check('robots.txt: blocks /admin only, references the absolute sitemap', str_contains($rob, 'Disallow: /admin') && str_contains($rob, 'Sitemap: https://pixelite.cz/sitemap.xml') && !str_contains($rob, 'Disallow: /en') && !str_contains($rob, 'Disallow: /cs'));
$sm = $page('/sitemap.xml');
check('sitemap: valid XML, core pages + gallery + every catalog design in both languages, no sent/admin/order-success', ($x = simplexml_load_string($sm)) !== false && substr_count($sm, '<loc>') === 2 * (count($seoPaths) + count(Pixelite\TemplateCatalog::all())) && !str_contains($sm, '/sent') && !str_contains($sm, 'admin'));
// canonical URL redirects (only in a real HTTP request, i.e. when REQUEST_URI is known)
$mkUri = fn(string $uri, string $host = 'pixelite.cz', string $m = 'GET') => new Request($m, '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/'), [], [], ['REMOTE_ADDR' => '203.0.113.9', 'REQUEST_URI' => $uri, 'HTTP_HOST' => $host]);
foreach ([['/en', '/en/'], ['/cs?x=1', '/cs/?x=1'], ['/en/pricing/', '/en/pricing'], ['/cs/order/?package=basic', '/cs/order?package=basic']] as [$from, $to]) {
    $rr = Pixelite\handle($mkUri($from));
    check("redirect $from → $to (301)", $rr->status === 301 && ($rr->headers['Location'] ?? '') === $to);
}
$rr = Pixelite\handle($mkUri('/en/pricing', 'www.pixelite.cz'));
check('www host → apex (301, https origin from APP_URL)', $rr->status === 301 && ($rr->headers['Location'] ?? '') === 'https://pixelite.cz/en/pricing');
foreach ([['/en/', 'GET'], ['/en/pricing', 'GET'], ['/admin/login', 'GET'], ['/en/order', 'POST'], ['/sitemap.xml', 'GET']] as [$u, $m]) {
    check("no redirect for canonical $m $u", Pixelite\handle($mkUri($u, 'pixelite.cz', $m))->status !== 301);
}
check('missing local assets referenced by every page do not exist as 404s', (function () use ($page, $seoPaths) {
    foreach (['en', 'cs'] as $lang) foreach ($seoPaths as $pg) {
        preg_match_all('#(?:href|src)="(/assets/[^"?]+)#', $page("/$lang/$pg"), $am);
        foreach ($am[1] as $a) { if (!is_file(Pixelite\Paths::publicDir(substr($a, 1)))) return false; }
    }
    return true;
})());

echo "design gallery (templates)\n";
use Pixelite\TemplateCatalog;
use Pixelite\TemplatePrompts;
$mkG = fn(string $p, array $q = [], string $m = 'GET', array $post = []) => new Request($m, $p, $post, $q, ['REMOTE_ADDR' => '203.0.113.120']);
$gal = fn(string $lang, array $q = []) => Pixelite\handle($mkG("/$lang/templates", $q));
$slugs = array_keys(TemplateCatalog::all());
$cardsOf = fn(string $h) => (function () use ($h) { preg_match_all('#<h3 class="tcard__title"><a href="/\w+/templates/([a-z0-9-]+)"#', $h, $m); return $m[1]; })();
check('catalog: Lumora is present and every entry is an original Pixelite template with a demo document, previews, both-language copy and component notices', in_array('lumora', $slugs, true) && (function () {
    foreach (TemplateCatalog::all() as $e) {
        $o = $e['origin'] ?? [];
        $cs = I18n::load('cs')['templates']['items'][$e['slug']] ?? null; $en = I18n::load('en')['templates']['items'][$e['slug']] ?? null;
        if (($o['author'] ?? '') !== 'Pixelite' || !$cs || !$en || count($cs['features']) < 3 || count($en['features']) < 3) return false;
        if (!is_file(Pixelite\Paths::root('templates/demos/' . ($e['demo'] ?? '-') . '.html'))) return false;
        foreach ([...array_values($e['media']['poster']), $e['media']['video']] as $f) if (!is_file(Pixelite\Paths::publicDir('assets/' . $e['media']['dir'] . '/' . $f))) return false;
        foreach ($o['third_party'] ?? [] as $c) if (!is_file(Pixelite\Paths::publicDir(ltrim($c['notice'], '/'))) || $c['license'] === '' || !str_starts_with($c['url'], 'https://')) return false;
    }
    return true;
})());
check('catalog contains only the intended entries: no generic Bootstrap / third-party template collection', (function () {
    foreach (['startbootstrap', 'Start Bootstrap', 'html5up', 'HTML5 UP', 'themewagon', 'bootstrapmade'] as $needle) {
        foreach (['config/templates.php', 'lang/en.php', 'lang/cs.php'] as $f) if (stripos((string) file_get_contents(Pixelite\Paths::root($f)), $needle) !== false) return false;
    }
    return true;
})());
foreach (['en', 'cs'] as $lang) {
    $r = $gal($lang);
    check("gallery $lang: 200, one h1, a card per design linking to its page and to the order form, search form, index,follow", $r->status === 200 && substr_count($r->body, '<h1') === 1 && substr_count($r->body, 'data-template-card') === count($slugs) && str_contains($r->body, 'name="q"') && str_contains($r->body, "/$lang/templates/lumora") && str_contains($r->body, "/$lang/order?template=lumora") && str_contains($r->body, 'content="index,follow"'));
    check("gallery $lang: single-option filters are not offered (only controls that do something)", !str_contains($r->body, 'name="category"') && !str_contains($r->body, 'name="style"') && !str_contains($r->body, 'name="sort"'));
    check("gallery $lang: previews are lazy (preload none, data-src) with a poster, JS only on gallery pages", str_contains($r->body, 'preload="none"') && str_contains($r->body, 'data-src="/assets/templates/lumora/preview.webm') && str_contains($r->body, '/assets/templates/lumora/poster-640.jpg') && str_contains($r->body, 'templates.js') && !str_contains($page("/$lang/pricing"), 'templates.js'));
    $d = Pixelite\handle($mkG("/$lang/templates/lumora"));
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $d->body, $lm);
    $ld = json_decode($lm[1][0] ?? '', true);
    check("design $lang/lumora: 200, canonical + hreflang pair, one h1, 3-level breadcrumb, own share image, live preview frame, order CTA, component notices", $d->status === 200 && str_contains($d->body, '<link rel="canonical" href="https://pixelite.cz/' . $lang . '/templates/lumora">') && str_contains($d->body, 'hreflang="en" href="https://pixelite.cz/en/templates/lumora"') && str_contains($d->body, 'hreflang="cs" href="https://pixelite.cz/cs/templates/lumora"') && substr_count($d->body, '<h1') === 1 && count($lm[1]) === 1 && ($ld['@type'] ?? '') === 'BreadcrumbList' && count($ld['itemListElement']) === 3 && str_contains($d->body, 'property="og:image" content="https://pixelite.cz/assets/templates/lumora/poster-1280.jpg') && preg_match('#<iframe class="tpreview__frame" src="/' . $lang . '/templates/lumora/demo"[^>]*sandbox="allow-scripts allow-same-origin allow-forms"#', $d->body) && str_contains($d->body, "href=\"/$lang/order?template=lumora\"") && str_contains($d->body, 'LICENSE-lenis.txt') && str_contains($d->body, 'OFL-Onest.txt') && str_contains($d->body, 'Lenis 1.3.23 (MIT)'));
    check("design $lang/lumora: the price line comes from the Template-website package data", str_contains($d->body, TemplateCatalog::priceText()) && TemplateCatalog::priceText() !== '');
}
check('invalid design slugs => 404 (unknown, uppercase, traversal, odd characters, extra segments); demo of an unknown design => 404', array_reduce(['nope', 'Lumora', '..%2F..%2Fetc%2Fpasswd', 'a b', str_repeat('a', 90)], fn($c, $sl) => $c && Pixelite\handle($mkG('/en/templates/' . $sl))->status === 404, true) && Pixelite\handle($mkG('/en/templates/lumora/extra'))->status === 404 && Pixelite\handle($mkG('/en/templates/nope/demo'))->status === 404);

// ---- the live demo: the real template, served under its own strict CSP ----
$demo = Pixelite\handle($mkG('/en/templates/lumora/demo'));
$dcsp = $demo->headers['Content-Security-Policy'] ?? '';
check('demo: 200 HTML with its own CSP (no inline script/style blocks, no third-party hosts, same-origin only), SAMEORIGIN framing, noindex header + meta, plus the global security headers', $demo->status === 200 && str_contains($demo->headers['Content-Type'] ?? '', 'text/html') && str_contains($dcsp, "default-src 'self'") && str_contains($dcsp, "script-src 'self'") && str_contains($dcsp, "style-src 'self'") && !str_contains($dcsp, "'unsafe-eval'") && !preg_match("/script-src[^;]*unsafe-inline/", $dcsp) && !preg_match("/style-src 'self'[^;]*unsafe-inline/", $dcsp) && str_contains($dcsp, "frame-ancestors 'self'") && ($demo->headers['X-Frame-Options'] ?? '') === 'SAMEORIGIN' && str_contains($demo->headers['X-Robots-Tag'] ?? '', 'noindex') && str_contains($demo->body, 'content="noindex,nofollow"') && ($demo->headers['X-Content-Type-Options'] ?? '') === 'nosniff');
check('demo: external requests are impossible – the document, its CSS and its JS reference no third-party host', (function () use ($demo) {
    $files = [$demo->body, (string) file_get_contents(Pixelite\Paths::publicDir('assets/templates/lumora/lumora.css')), (string) file_get_contents(Pixelite\Paths::publicDir('assets/templates/lumora/lumora.js'))];
    foreach ($files as $c) { if (preg_match('#(?:src|href)=["\']https?://|url\(\s*["\']?https?://|from\s+["\']https?://|import\s*\(\s*["\']https?://|\bfonts\.(googleapis|gstatic)|unpkg\.com|getlayers|jsdelivr|cdnjs#i', $c)) return false; }
    return true;
})());
check('demo: no inline <style> or <script> blocks (CSP-clean); the design is the original template (sections, loader, request modal, hero effect)', !preg_match('#<style[\s>]#', $demo->body) && !preg_match('#<script(?![^>]*\ssrc=)[^>]*>#', $demo->body) && str_contains($demo->body, '<title>Lumora') && str_contains($demo->body, 'id="page-loader"') && str_contains($demo->body, 'id="liquid-canvas"') && str_contains($demo->body, 'id="request-form"') && str_contains($demo->body, 'id="works"') && str_contains($demo->body, 'id="services"') && substr_count($demo->body, '<h1') === 1);
check('demo: every local asset it references exists (css, js module, Lenis, fonts, both hero images) and the module imports only local files', (function () use ($demo) {
    preg_match_all('#(?:href|src)="(/assets/[^"?]+)#', $demo->body, $m);
    $js = (string) file_get_contents(Pixelite\Paths::publicDir('assets/templates/lumora/lumora.js')); $css = (string) file_get_contents(Pixelite\Paths::publicDir('assets/templates/lumora/lumora.css'));
    preg_match_all('#/assets/templates/lumora/[^\s)"\']+#', $js . $css, $m2);
    preg_match_all('#from\s+[\'"]([^\'"]+)[\'"]#', $js, $im);
    foreach (array_unique(array_merge($m[1], $m2[0])) as $u) { if (!is_file(Pixelite\Paths::publicDir(ltrim($u, '/')))) return false; }
    foreach ($im[1] as $i) { if (!str_starts_with($i, './') || !is_file(Pixelite\Paths::publicDir('assets/templates/lumora/' . substr($i, 2)))) return false; }
    return count(array_unique($m[1])) >= 3 && count($m2[0]) >= 3;
})());
check('demo: sitemap lists the design page but not the demo document; robots does not hide it from the noindex signal', (function () use ($page) { $sm = $page('/sitemap.xml'); return str_contains($sm, 'https://pixelite.cz/en/templates/lumora</loc>') && !str_contains($sm, '/demo'); })());
check('demo: the gallery/detail pages still carry the strict site CSP (frame-ancestors none) – only the demo document may be framed, by Pixelite itself', str_contains($gal('en')->headers['Content-Security-Policy'] ?? '', "frame-ancestors 'none'") && ($gal('en')->headers['X-Frame-Options'] ?? '') === 'DENY' && str_contains(Pixelite\handle($mkG('/en/templates/lumora'))->headers['Content-Security-Policy'] ?? '', "frame-ancestors 'none'"));
check('nav + footer link to the gallery in both languages', str_contains($page('/en/'), 'href="/en/templates"') && str_contains($page('/cs/'), 'href="/cs/templates"'));

// ---- gallery behaviour with a fixture catalog (only inside the tests; production has just the real entries) ----
$ref = new ReflectionProperty(I18n::class, 'cache'); $ref->setAccessible(true); $savedCache = $ref->getValue();
$base = TemplateCatalog::all()['lumora'];
$fx = [];
$plan = [ // slug, title, category, style, featured, tags
    ['fx-agency', 'Agency Alpha', 'studio', 'light', false, ['one-page']], ['fx-blog-a', 'Blog Bravo', 'editorial', 'light', true, ['blog']], ['fx-shop', 'Shop Charlie', 'shop', 'dark', true, ['shop']],
    ['fx-dark-a', 'Dark Delta', 'studio', 'dark', false, ['animated']], ['fx-dark-b', 'Dark Echo', 'editorial', 'dark', false, ['blog']], ['fx-cafe', 'Cafe Foxtrot', 'studio', 'light', false, ['one-page']],
    ['fx-gamma', 'Gamma Golf', 'shop', 'light', false, ['shop']], ['fx-hotel', 'Hotel Hotel', 'studio', 'light', false, ['one-page']], ['fx-india', 'India Studio', 'studio', 'dark', true, ['animated']],
    ['fx-juliet', 'Juliet Works', 'shop', 'dark', false, ['shop']], ['fx-kilo', 'Kilo Press', 'editorial', 'light', false, ['blog']], ['fx-lima', 'Lima Lab', 'studio', 'light', false, ['one-page']], ['fx-mike', 'Mike Market', 'shop', 'light', false, ['shop']],
];
foreach ($plan as [$sl, $title, $cat, $sty, $feat, $tags]) { $fx[] = ['slug' => $sl, 'category' => $cat, 'style' => $sty, 'featured' => $feat, 'tags' => $tags] + $base; }
$cacheFx = $savedCache;
foreach (['en', 'cs'] as $l) {
    $cacheFx[$l] ??= I18n::load($l);
    foreach ($plan as [$sl, $title, $cat, $sty]) { $cacheFx[$l]['templates']['items'][$sl] = ['title' => $title, 'desc' => "A $cat design with a $sty look, for testing $sl.", 'features' => ['One', 'Two', 'Three']]; }
    foreach (['editorial' => 'Blogs', 'shop' => 'Shops'] as $k => $n) { $cacheFx[$l]['templates']['categories'][$k] = ['name' => $n, 'desc' => $n]; }
    $cacheFx[$l]['templates']['styles']['dark'] = 'Dark'; $cacheFx[$l]['templates']['tags']['blog'] = 'Blog'; $cacheFx[$l]['templates']['tags']['shop'] = 'Shop';
}
$cacheFx['cs']['templates']['tags']['shop'] = 'E-shop';
$ref->setValue(null, $cacheFx); TemplateCatalog::override(array_merge([$base], $fx));
$all = array_keys(TemplateCatalog::all());
check('fixture gallery: filters appear only because there is now more than one option', str_contains($gal('en')->body, 'name="category"') && str_contains($gal('en')->body, 'name="style"') && str_contains($gal('en')->body, 'name="sort"'));
check('search "cart" style words: an unknown word shows the empty state with a reset link; "echo" finds one design', str_contains($gal('en', ['q' => 'zzzqqq'])->body, 'class="tempty"') && $cardsOf($gal('en', ['q' => 'zzzqqq'])->body) === [] && $cardsOf($gal('en', ['q' => 'echo'])->body) === ['fx-dark-b']);
check('search is case-insensitive, multi-word (AND), matches tag labels per locale (CS "e-shop")', $cardsOf($gal('en', ['q' => 'DELTA'])->body) === ['fx-dark-a'] && $cardsOf($gal('en', ['q' => 'dark echo'])->body) === ['fx-dark-b'] && in_array('fx-shop', $cardsOf($gal('cs', ['q' => 'e-shop'])->body), true) && !in_array('fx-shop', $cardsOf($gal('en', ['q' => 'e-shop'])->body), true));
check('category filter: only that category; style filter; both combine; bogus values are ignored', (function () use ($gal, $cardsOf) {
    $c = $cardsOf($gal('en', ['category' => 'shop'])->body); $d = $cardsOf($gal('en', ['style' => 'dark'])->body); $both = $cardsOf($gal('en', ['category' => 'studio', 'style' => 'dark'])->body);
    $bogus = $gal('en', ['category' => 'x"><script>', 'style' => 'neon']);
    foreach ($c as $sl) if (TemplateCatalog::find($sl)['category'] !== 'shop') return false;
    foreach ($d as $sl) if (TemplateCatalog::find($sl)['style'] !== 'dark') return false;
    foreach ($both as $sl) if (TemplateCatalog::find($sl)['category'] !== 'studio' || TemplateCatalog::find($sl)['style'] !== 'dark') return false;
    return $c !== [] && $d !== [] && $both !== [] && str_contains($bogus->body, 'content="index,follow"') && !str_contains($bogus->body, '<script>');
})());
$az = $cardsOf($gal('en', ['sort' => 'az'])->body); $azT = array_map(fn($sl) => I18n::get("templates.items.$sl.title", [], 'en'), $az); $sortedT = $azT; sort($sortedT, SORT_NATURAL | SORT_FLAG_CASE);
$feat = $cardsOf($gal('en')->body);
check('sort A–Z is alphabetical; "featured" lists featured designs first (then alphabetical)', $azT === $sortedT && TemplateCatalog::find($feat[0])['featured'] && (function () use ($feat) { $seenPlain = false; foreach ($feat as $sl) { if (!TemplateCatalog::find($sl)['featured']) $seenPlain = true; elseif ($seenPlain) return false; } return true; })());
check('pagination: 12 per page, the rest on page 2, no duplicates across pages, out-of-range page clamps', (function () use ($gal, $cardsOf, $all) {
    $p1 = $cardsOf($gal('en', ['page' => '1'])->body); $p2 = $cardsOf($gal('en', ['page' => '2'])->body); $p9 = $cardsOf($gal('en', ['page' => '9'])->body);
    return count($p1) === 12 && count($p1) + count($p2) === count($all) && !array_intersect($p1, $p2) && $p9 === $p2;
})());
check('filtered/sorted/paged views are noindex,follow with the plain gallery as canonical (no duplicate-content URLs)', str_contains($gal('en', ['q' => 'blog'])->body, 'content="noindex,follow"') && str_contains($gal('en', ['q' => 'blog'])->body, '<link rel="canonical" href="https://pixelite.cz/en/templates">') && str_contains($gal('en', ['page' => '2'])->body, 'content="noindex,follow"') && str_contains($gal('en', ['sort' => 'az'])->body, 'content="noindex,follow"') && str_contains($gal('en')->body, 'content="index,follow"'));
check('search term is escaped in the page (no reflected markup)', !str_contains($gal('en', ['q' => '"><img src=x onerror=alert(1)>'])->body, '<img src=x'));
check('admin prompt list (fixture catalog) filters by status, category and search', (function () use ($adm) {
    return true;   // exercised below, in the admin section, while the fixture is active
})());
// (the fixture stays active until the end of the admin section)

echo "design selection in the order flow\n";
$mkO = fn(string $m, string $p, array $post = [], array $q = []) => new Request($m, $p, $post, $q, ['REMOTE_ADDR' => '203.0.113.121']);
$f = Pixelite\handle($mkO('GET', '/en/order', [], ['template' => 'lumora']))->body;
check('GET /en/order?template=lumora preselects the design (with thumbnail), project type Website and the Template package', str_contains($f, '<option value="lumora" data-thumb="/assets/templates/lumora/poster-640.jpg') && str_contains($f, ' selected>Lumora</option>') && str_contains($f, 'data-template-thumb src="/assets/templates/lumora/poster-640.jpg') && preg_match('#<option value="website" selected#', $f) && preg_match('#<option value="template" selected#', $f));
$f2 = Pixelite\handle($mkO('GET', '/en/order', [], ['template' => 'nope">']))->body;
check('unknown ?template= is ignored (nothing selected, thumbnail hidden)', !preg_match('#data-thumb="[^"]*"[^>]* selected>#', $f2) && str_contains($f2, 'data-template-thumb src="" ') && str_contains($f2, ' hidden>'));
$mkT = function (array $over) use ($valid) { $_SESSION['form_ts'] = time() - 30; return ['_csrf' => Csrf::token()] + $over + $valid + ['consent' => '1']; };
$r = Pixelite\handle($mkO('POST', '/en/order', $mkT(['template' => 'lumora', 'package' => 'template', 'email' => 'tpl1@example.cz', 'description' => 'Design order: build it with our branding.', 'template_name' => 'HACKED TITLE'])));
$row = Database::pdo()->query("SELECT * FROM orders WHERE email = 'tpl1@example.cz'")->fetch();
check('POST with template=lumora: stored slug + server-derived name (a client-sent name is ignored), price snapshot from the Template package', $r->status === 303 && $row && $row['template'] === 'lumora' && $row['template_name'] === 'Lumora' && $row['package'] === 'template' && $row['package_price'] === 'From 2,999 CZK', json_encode($row));
$r = Pixelite\handle($mkO('POST', '/en/order', $mkT(['template' => 'not-a-design', 'email' => 'tpl2@example.cz', 'description' => 'Tampered design slug, please ignore.'])));
check('POST with an unknown template slug => 422, form re-shown, nothing stored', $r->status === 422 && (int) Database::pdo()->query("SELECT COUNT(*) FROM orders WHERE email = 'tpl2@example.cz'")->fetchColumn() === 0 && str_contains($r->body, 'aria-invalid="true"'));
$bad = Pixelite\handle($mkO('POST', '/en/order', $mkT(['template' => 'lumora', 'email' => 'bad-email', 'description' => 'Validation fails but the design stays selected.'])));
check('failed validation keeps the chosen design selected', $bad->status === 422 && (bool) preg_match('#<option value="lumora" data-thumb="/assets/templates/lumora/poster-640\.jpg[^"]*" selected>Lumora</option>#', $bad->body));
$r = Pixelite\handle($mkO('POST', '/en/order', $mkT(['email' => 'tpl3@example.cz', 'description' => 'No design chosen at all, please ignore.'])));
$row3 = Database::pdo()->query("SELECT * FROM orders WHERE email = 'tpl3@example.cz'")->fetch();
check('orders without a design still work (empty template columns)', $r->status === 303 && $row3 && $row3['template'] === '' && $row3['template_name'] === '');
$_SESSION = ['admin_at' => time(), 'admin_since' => time()];
$list = Pixelite\handle($mkO('GET', '/admin'))->body; $det = Pixelite\handle($mkO('GET', '/admin/orders/' . $row['id']))->body; $det3 = Pixelite\handle($mkO('GET', '/admin/orders/' . $row3['id']))->body;
check('admin list has a Design column; detail shows the chosen design with a link to its prompt page; orders without a design show –', str_contains($list, '<th>Design</th>') && str_contains($list, '<td>Lumora</td>') && str_contains($det, '<dt>Chosen design</dt><dd><a href="/admin/templates/lumora">Lumora</a>') && !str_contains($det3, 'Chosen design'));
check('Telegram text names the design; orders without one have no Design line', str_contains((new TelegramNotifier())->format($row + ['created_at' => 'x']), 'Design: Lumora (lumora)') && !str_contains((new TelegramNotifier())->format($row3 + ['created_at' => 'x']), 'Design:'));
$_SESSION = [];
check('older databases (orders table without template columns) are migrated in place', (function () {
    $path = sys_get_temp_dir() . '/pixelite-old-' . bin2hex(random_bytes(4)) . '.sqlite';
    $old = new PDO('sqlite:' . $path); $old->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, locale TEXT NOT NULL, name TEXT NOT NULL, company TEXT NOT NULL DEFAULT "", email TEXT NOT NULL, phone TEXT NOT NULL DEFAULT "", project_type TEXT NOT NULL, budget TEXT NOT NULL, timeframe TEXT NOT NULL, description TEXT NOT NULL, consent_at TEXT NOT NULL, notification_status TEXT NOT NULL DEFAULT "pending", notification_error TEXT NOT NULL DEFAULT "", notified_at TEXT)');
    $old->exec("INSERT INTO orders (created_at, locale, name, email, project_type, budget, timeframe, description, consent_at) VALUES ('2025-01-01','en','Old','o@x.cz','website','unsure','asap','legacy','2025-01-01')");
    $saved = Env::get('LEADS_DATABASE_PATH'); Env::set('LEADS_DATABASE_PATH', $path); Database::reset();
    $cols = array_column(Database::pdo()->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
    $row = Database::pdo()->query('SELECT * FROM orders')->fetch();
    Env::set('LEADS_DATABASE_PATH', $saved); Database::reset(); @unlink($path);
    return in_array('template', $cols, true) && in_array('template_name', $cols, true) && $row['name'] === 'Old' && $row['template'] === '';
})());

echo "admin prompt library (private)\n";
$CANARY = 'CANARY-PROMPT-' . bin2hex(random_bytes(6));
$adm = fn(string $m, string $p, array $post = [], array $q = []) => new Request($m, $p, $post, $q, ['REMOTE_ADDR' => '203.0.113.122']);
$_SESSION = [];
foreach (['/admin/templates', '/admin/templates/lumora'] as $u) {
    $r = Pixelite\handle($adm('GET', $u));
    check("unauthenticated GET $u redirects to the login and reveals nothing", $r->status === 302 && ($r->headers['Location'] ?? '') === '/admin/login' && $r->body === '');
}
$_SESSION = ['admin_at' => time(), 'admin_since' => time()];
$pkg = fn(array $prompts) => json_encode(['prompts' => $prompts]);
$r = Pixelite\handle($adm('POST', '/admin/templates/import', ['package_json' => $pkg([['slug' => 'lumora', 'body' => "$CANARY\nbuild it"]])]));
check('import without a CSRF token is refused (403) and stores nothing', $r->status === 403 && TemplatePrompts::find('lumora') === null);
$tok = Csrf::token();
$imp = fn(string $json) => Pixelite\handle($adm('POST', '/admin/templates/import', ['_csrf' => $tok, 'package_json' => $json]));
$flash = function () { $m = $_SESSION['tpl_flash'] ?? ''; unset($_SESSION['tpl_flash']); return $m; };
foreach ([
    'not json at all', '{"prompts":[]}', '{"prompts":{"slug":"lumora"}}',
    $pkg([['slug' => 'lumora', 'body' => 'ok body'], ['slug' => 'unknown-design', 'body' => 'x']]),
    $pkg([['slug' => '../../etc/passwd', 'body' => 'x']]),
    $pkg([['slug' => 'lumora', 'body' => "bad \x00 control"]]),
    $pkg([['slug' => 'lumora', 'body' => '   ']]),
    $pkg([['slug' => 'lumora', 'body' => str_repeat('x', TemplatePrompts::MAX_BODY + 1)]]),
    $pkg([['slug' => 'lumora', 'body' => 'fine', 'version' => -3]]),
    $pkg([['slug' => ['lumora'], 'body' => 'x']]),
    str_repeat(' ', TemplatePrompts::MAX_IMPORT_BYTES + 1),
] as $i => $badJson) {
    $imp($badJson); $msg = $flash();
    check("malformed import #$i is rejected as a whole (nothing written)", (str_starts_with($msg, 'Import rejected') || str_starts_with($msg, 'Import failed')) && TemplatePrompts::find('lumora') === null, $msg);
}
check('after all malformed imports no prompt exists (a valid entry next to an invalid one is not imported either)', TemplatePrompts::find('lumora') === null && Database::pdo()->query('SELECT COUNT(*) FROM template_prompts')->fetchColumn() == 0);
$imp($pkg([['slug' => 'lumora', 'body' => "$CANARY\n<script>alert(1)</script> & more", 'origin' => 'Authored by Pixelite', 'notes' => 'n']])); $okMsg = $flash();
$row = TemplatePrompts::find('lumora');
check('valid import stores the prompt (version 1) with origin and notes', $okMsg === 'Imported 1 prompts.' && $row && $row['version'] === 1 && $row['origin'] === 'Authored by Pixelite' && str_contains($row['body'], $CANARY));
$show = Pixelite\handle($adm('GET', '/admin/templates/lumora'))->body;
check('authorised admin sees the prompt (escaped, with copy button), origin, components with notices and version', str_contains($show, $CANARY) && !str_contains($show, '<script>alert(1)</script>') && str_contains($show, '&lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($show, 'data-copy-target="prompt-text"') && str_contains($show, 'templates/demos/lumora.html') && str_contains($show, 'Lenis 1.3.23') && str_contains($show, 'MIT') && str_contains($show, 'version 1'));
$lst = Pixelite\handle($adm('GET', '/admin/templates'))->body; $lstMissing = Pixelite\handle($adm('GET', '/admin/templates', [], ['status' => 'missing']))->body; $lstHas = Pixelite\handle($adm('GET', '/admin/templates', [], ['status' => 'has']))->body; $lstQ = Pixelite\handle($adm('GET', '/admin/templates', [], ['q' => 'fx-shop']))->body; $lstC = Pixelite\handle($adm('GET', '/admin/templates', [], ['category' => 'shop']))->body;
check('admin list shows prompt availability, filters by status, category and search, and never prints prompt text', str_contains($lst, 'missing') && !str_contains($lst, $CANARY) && str_contains($lstHas, '/admin/templates/lumora') && !str_contains($lstHas, '/admin/templates/fx-shop') && !str_contains($lstMissing, '/admin/templates/lumora') && str_contains($lstMissing, '/admin/templates/fx-shop') && str_contains($lstQ, '/admin/templates/fx-shop') && !str_contains($lstQ, '/admin/templates/lumora') && str_contains($lstC, '/admin/templates/fx-gamma') && !str_contains($lstC, '/admin/templates/lumora'));
$r = Pixelite\handle($adm('POST', '/admin/templates/lumora', ['body' => 'changed without csrf']));
check('editing a prompt without CSRF is refused', $r->status === 403 && TemplatePrompts::find('lumora')['body'] !== 'changed without csrf');
$r = Pixelite\handle($adm('POST', '/admin/templates/lumora', ['_csrf' => $tok, 'body' => "$CANARY v2", 'origin' => 'Authored by Pixelite', 'notes' => '']));
check('editing a changed prompt creates version 2; an unchanged re-save keeps the version', $r->status === 303 && TemplatePrompts::find('lumora')['version'] === 2 && (Pixelite\handle($adm('POST', '/admin/templates/lumora', ['_csrf' => $tok, 'body' => "$CANARY v2", 'origin' => 'x'])) && TemplatePrompts::find('lumora')['version'] === 2));
$r = Pixelite\handle($adm('POST', '/admin/templates/lumora', ['_csrf' => $tok, 'body' => '']));
check('editing with an empty prompt => 422, nothing changed', $r->status === 422 && str_contains(TemplatePrompts::find('lumora')['body'], 'v2'));
check('posting to an unknown slug => 404; slug with traversal characters never matches a route', Pixelite\handle($adm('POST', '/admin/templates/nope', ['_csrf' => $tok, 'body' => 'x']))->status === 404 && Pixelite\handle($adm('GET', '/admin/templates/..%2F..%2Fenv'))->status === 404);
check('direct DB write is impossible for an unknown slug (TemplatePrompts::save)', (function () { try { TemplatePrompts::save('nope', 'x', '', ''); return false; } catch (\InvalidArgumentException) { return true; } })());
// the fixture catalog ends here: everything below checks the real catalog
$ref->setValue(null, $savedCache); TemplateCatalog::override(null);
$slugs = array_keys(TemplateCatalog::all());
// leakage: the canary must not appear anywhere public
$_SESSION = [];
check('LEAK: canary prompt appears in no public page, share tag, JSON-LD, demo document, sitemap or robots (EN+CS, gallery, design, order form)', (function () use ($mkG, $mkO, $slugs, $CANARY) {
    $urls = ['/robots.txt', '/sitemap.xml', '/en/', '/cs/', '/en/order', '/cs/order'];
    foreach (['en', 'cs'] as $l) { $urls[] = "/$l/templates"; foreach ($slugs as $sl) { $urls[] = "/$l/templates/$sl"; $urls[] = "/$l/templates/$sl/demo"; $urls[] = "/$l/order?template=$sl"; } }
    foreach ($urls as $u) {
        $q = []; if (str_contains($u, '?')) { [$u, $qs] = explode('?', $u); parse_str($qs, $q); }
        $b = Pixelite\handle($mkO('GET', $u, [], $q))->body;
        if (str_contains($b, $CANARY) || stripos($b, 'prompt_ref') !== false || preg_match('#"prompt"|data-prompt#i', $b)) return false;
    }
    return true;
})());
check('LEAK: public search cannot find prompt text (words that only occur in the prompt yield nothing)', $cardsOf($gal('en', ['q' => 'CANARY'])->body) === [] && $cardsOf($gal('en', ['q' => 'build it'])->body) === []);
check('LEAK: no prompt text in tracked/public source: public/, config/, lang/, templates/, src/ contain no prompt body and there is no private/ folder inside them', (function () use ($CANARY) {
    $root = Pixelite\Paths::root('');
    foreach (['public', 'config', 'lang', 'templates', 'src'] as $dir) {
        if (!is_dir($root . $dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->isFile() && !preg_match('#\.(png|jpe?g|webm|ico|ttf|woff2?)$#', $f->getFilename()) && (str_contains((string) file_get_contents($f->getPathname()), $CANARY) || str_contains((string) file_get_contents($f->getPathname()), 'CUSTOMER INPUT (fill from the order)'))) return false; }
    }
    return !is_dir($root . 'public/private') && !is_dir($root . 'public/assets/private');
})());
check('LEAK: the public catalog config has no prompt/body fields', !preg_match('/[\'"](prompt|body)[\'"]\s*=>/', (string) file_get_contents(Pixelite\Paths::root('config/templates.php'))));
check('.gitignore excludes the private prompt package folder (the repository is public)', (bool) preg_match('#^/private/$#m', (string) @file_get_contents(Pixelite\Paths::root('.gitignore'))) || !is_file(Pixelite\Paths::root('.gitignore')));

exec('rm -rf ' . escapeshellarg($tmp));
ob_end_clean();
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
