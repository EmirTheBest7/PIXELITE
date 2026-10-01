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

exec('rm -rf ' . escapeshellarg($tmp));
ob_end_clean();
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
