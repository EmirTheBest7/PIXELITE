<?php
declare(strict_types=1);

// Included by tests/editor.php (shares check(), $tmp, $mkProject, $as …). PUBLIC site builder: logged-out access, temporary anonymous workspaces, isolation between
// visitors and from customer data, CSRF, rate limits and quotas, uploads, hostile content, expiry/cleanup, concurrency, the feature switch and the public pages.

use Pixelite\Editor\Builder\Anon\AnonWorkspaces;
use Pixelite\Editor\Builder\{HtmlSanitizer, SiteStore};
use Pixelite\Editor\Media;
use Pixelite\{Customers, Database, Env, Paths, Projects, Request};

echo "\npublic site builder (temporary anonymous workspaces)\n";
$BD = "$tmp/builder-" . bin2hex(random_bytes(3));
Env::set('BUILDER_DIR', $BD);
Env::set('BUILDER_PUBLIC', '');          // unset = ON by default (production needs no flag)
Env::set('EDITOR_ENABLED', 'true');      // the authenticated builder coexists
Env::set('BUILDER_MAX_WORKSPACES', '');
Env::set('BUILDER_MAX_BYTES', '');
Env::set('APP_URL', 'https://pixelite.cz');
Database::reset();
Database::pdo()->exec('DELETE FROM rate_limits');
$_SESSION = [];
$_COOKIE = [];

$vi = 0;
$pr = fn(string $m, string $p, array $post = [], array $hdr = [], array $files = [], string $ip = '198.51.100.10') => new Request($m, $p, $post, [], ['REMOTE_ADDR' => $ip, 'REQUEST_URI' => $p] + $hdr, $files);
$ph = function (Request $r) { return Pixelite\handle($r); };
$cookieOf = function (Pixelite\Response $r): ?string { $c = (string) ($r->headers['Set-Cookie'] ?? ''); return preg_match('/^(?:__Host-)?pixelite_bw=([^;]+);/', $c, $m) ? $m[1] : null; };
$as_visitor = function (?string $cookie, bool $https = false): void { $_SESSION = []; $_COOKIE = $cookie === null ? [] : [($https ? '__Host-pixelite_bw' : 'pixelite_bw') => $cookie]; };
$landingToken = function (string $ip = '198.51.100.10') use ($pr, $ph, $as_visitor): string { $as_visitor(null); $b = $ph($pr('GET', '/en/builder', [], [], [], $ip))->body; preg_match('/name="_start" value="([a-f0-9]{64})"/', $b, $m); return $m[1] ?? ''; };
/** A new visitor: loads the landing page, presses "Start building". @return array{cookie:string,key:string,secret:string,csrf:string} */
$visitor = function (string $ip = '198.51.100.10') use ($pr, $ph, $as_visitor, $cookieOf, $landingToken): array {
    $tok = $landingToken($ip);
    $as_visitor(null);
    $r = $ph($pr('POST', '/en/builder', ['_start' => $tok, 'action' => 'start'], [], [], $ip));
    $c = $cookieOf($r);
    if ($r->status !== 303 || $c === null) { fwrite(STDOUT, "     start failed: {$r->status}\n"); return ['cookie' => '', 'key' => '', 'secret' => '', 'csrf' => '']; }
    [$key, $secret] = explode('.', $c);
    return ['cookie' => $c, 'key' => $key, 'secret' => $secret, 'csrf' => AnonWorkspaces::csrf($key, $secret)];
};
$XHR = ['HTTP_X_REQUESTED_WITH' => 'pixelite-editor'];
/** call as a visitor */
$vcall = function (array $v, string $m, string $path, array $post = [], ?array $hdr = null, array $files = [], ?string $csrf = null, string $ip = '198.51.100.10') use ($pr, $ph, $as_visitor, $XHR) {
    $as_visitor($v['cookie'] === '' ? null : $v['cookie']);
    if ($m === 'POST') { $post += ['_csrf' => $csrf ?? $v['csrf']]; }
    return $ph($pr($m, $path, $post, $hdr ?? ($m === 'POST' ? $XHR : []), $files, $ip));
};
$jv = fn(Pixelite\Response $r) => json_decode($r->body, true);
$png = function (int $w = 40, int $h = 30): string { $im = imagecreatetruecolor($w, $h); imagefill($im, 0, 0, (int) imagecolorallocate($im, 10, 120, 200)); ob_start(); imagepng($im); return (string) ob_get_clean(); };
$upload = function (array $v, string $bytes, string $ip = '198.51.100.10') use ($vcall, $as_visitor, $pr, $XHR, $tmp) {
    $f = "$tmp/pu-" . bin2hex(random_bytes(3)); file_put_contents($f, $bytes);
    $as_visitor($v['cookie']);
    $r = $pr('POST', '/builder/w/media', ['_csrf' => $v['csrf']], $XHR, ['image' => ['name' => 'a.png', 'tmp_name' => $f, 'size' => strlen($bytes), 'error' => 0]], $ip);
    return (new Pixelite\Editor\Builder\Anon\PublicBuilderController(false))->uploadMedia($r, []);   // no real HTTP upload in the test: verification off
};

/* ---------------------------------------------------------------- 1. logged-out visitor: public entry point */
$land = $ph($pr('GET', '/en/builder'));
check('public entry: /en/builder is open to a logged-out visitor (200, indexable landing, never cached, carries the start token)', $land->status === 200 && str_contains($land->body, 'name="_start"') && ($land->headers['Cache-Control'] ?? '') === 'no-store' && str_contains($land->body, 'content="index,follow"'));
check('public entry exists in every language (en, cs, ru, uk) with a localized title and the start form', (function () use ($pr, $ph) { foreach (['en' => 'Website builder', 'cs' => 'Tvůrce webu', 'ru' => 'Конструктор сайтов', 'uk' => 'Конструктор сайтів'] as $lg => $title) { $r = $ph($pr('GET', "/$lg/builder")); if ($r->status !== 200 || !str_contains($r->body, "<html lang=\"$lg\">") || !str_contains($r->body, $title) || !str_contains($r->body, 'name="_start"')) { return false; } } return true; })());
check('"Builder" is linked from the main navigation of public pages in every language (new design and legacy)', (function () use ($pr, $ph) { foreach (['en', 'cs', 'ru', 'uk'] as $lg) { foreach (["/$lg/pricing", "/legacy/$lg/pricing"] as $path) { $b = $ph($pr('GET', $path))->body; if (!str_contains($b, 'href="' . (str_starts_with($path, '/legacy') ? "/legacy/$lg/builder" : "/$lg/builder") . '"')) { fwrite(STDOUT, "     no link on $path\n"); return false; } } } return true; })());
check('the legacy-design landing page renders too, and the sitemap lists the builder in every language', $ph($pr('GET', '/legacy/en/builder'))->status === 200 && (function () use ($pr, $ph) { $x = $ph($pr('GET', '/sitemap.xml'))->body; foreach (['en', 'cs', 'ru', 'uk'] as $lg) { if (!str_contains($x, "<loc>https://pixelite.cz/$lg/builder</loc>")) return false; } return true; })());
check('the cookie policy lists the builder cookie with its purpose in every language', (function () use ($pr, $ph) { foreach (['en', 'cs', 'ru', 'uk'] as $lg) { $b = $ph($pr('GET', "/$lg/cookies"))->body; if (!str_contains($b, 'pixelite_bw')) return false; } return true; })());

/* ---------------------------------------------------------------- 2. starting a workspace */
check('start needs the start token (missing/forged → 403, no workspace, no cookie) and refuses cross-site fetches', (function () use ($pr, $ph, $as_visitor, $landingToken, $BD) {
    $as_visitor(null);
    $a = $ph($pr('POST', '/en/builder', ['action' => 'start'])); $b = $ph($pr('POST', '/en/builder', ['_start' => str_repeat('a', 64), 'action' => 'start']));
    $tok = $landingToken(); $as_visitor(null);
    $c = $ph($pr('POST', '/en/builder', ['_start' => $tok, 'action' => 'start'], ['HTTP_SEC_FETCH_SITE' => 'cross-site']));
    $tok = $landingToken(); $as_visitor(null);
    $same = $ph($pr('POST', '/en/builder', ['_start' => $tok, 'action' => 'start'], ['HTTP_SEC_FETCH_SITE' => 'same-site'], [], '198.51.100.5'));
    $evil = $ph($pr('POST', '/en/builder', ['_start' => $tok, 'action' => 'start'], ['HTTP_ORIGIN' => 'https://evil.example'], [], '198.51.100.5'));
    if ($same->status !== 403 || $evil->status !== 403) { return false; }
    return $a->status === 403 && $b->status === 403 && $c->status === 403 && !isset($a->headers['Set-Cookie']) && !isset($c->headers['Set-Cookie']) && AnonWorkspaces::all() === [];
})());
$A = $visitor();
$cookieHeader = '';
check('start with the token creates a workspace and sets ONE hardened cookie (httpOnly, SameSite=Lax, 24 h, path /; no PHP session cookie)', (function () use ($A, $BD, $pr, $ph, $as_visitor, $landingToken, &$cookieHeader) {
    $tok = $landingToken('198.51.100.11'); $as_visitor(null);
    $r = $ph($pr('POST', '/en/builder', ['_start' => $tok, 'action' => 'start'], [], [], '198.51.100.11'));
    $cookieHeader = (string) $r->headers['Set-Cookie'];
    return $A['key'] !== '' && preg_match('/^w[a-f0-9]{32}$/', $A['key']) === 1 && preg_match('/^[a-f0-9]{32}$/', $A['secret']) === 1 && ($r->headers['Location'] ?? '') === '/builder/w'
        && str_contains($cookieHeader, 'HttpOnly') && str_contains($cookieHeader, 'SameSite=Lax') && str_contains($cookieHeader, 'Max-Age=86400') && str_contains($cookieHeader, 'Path=/') && !str_contains($cookieHeader, 'Secure') && !str_contains($cookieHeader, 'PHPSESSID') && !str_contains($cookieHeader, 'Domain=')
        && is_dir("$BD/{$A['key']}") && is_file("$BD/{$A['key']}/meta.json") && !str_contains((string) file_get_contents("$BD/{$A['key']}/meta.json"), $A['secret']);
})());
check('over HTTPS the cookie is __Host- prefixed and Secure', (function () use ($pr, $ph, $as_visitor, $cookieOf) {
    $b = $ph($pr('GET', '/en/builder', [], ['HTTPS' => 'on']))->body; preg_match('/name="_start" value="([a-f0-9]{64})"/', $b, $m);
    $as_visitor(null); $r = $ph($pr('POST', '/en/builder', ['_start' => $m[1], 'action' => 'start'], ['HTTPS' => 'on'], [], '198.51.100.12'));
    $h = (string) ($r->headers['Set-Cookie'] ?? ''); return str_starts_with($h, '__Host-pixelite_bw=') && str_contains($h, '; Secure') && !str_contains($h, 'Domain=');
})());
check('the workspace lives under storage/builder/<key> (outside the web root and outside storage/projects), the secret is stored only as a hash', str_starts_with(AnonWorkspaces::dir($A['key']), realpath($BD) ?: $BD) && !str_contains(AnonWorkspaces::dir($A['key']), '/projects/') && !str_starts_with(AnonWorkspaces::dir($A['key']), Paths::publicDir('')) && hash_equals(json_decode((string) file_get_contents("$BD/{$A['key']}/meta.json"), true)['h'], hash('sha256', $A['secret'])));
check('the starter page is safe: the sanitiser keeps it unchanged (no notes)', (function () { $s = require Paths::root('config/builder-starter.php'); $r = (new HtmlSanitizer('/builder/w/media'))->body($s['html']); return $r !== null && $r['notes'] === []; })());

/* ---------------------------------------------------------------- 3. using the builder without logging in */
$shell = $vcall($A, 'GET', '/builder/w');
$sc = (string) ($shell->headers['Content-Security-Policy'] ?? '');
check('anonymous: the builder page opens (200), strict page CSP (script-src self, no eval/inline), not frameable, never cached, noindex, cookie refreshed', $shell->status === 200 && str_contains($sc, "script-src 'self';") && !str_contains($sc, 'unsafe-eval') && !preg_match("/script-src[^;]*unsafe-inline/", $sc) && str_contains($sc, "frame-ancestors 'none'")
    && ($shell->headers['Cache-Control'] ?? '') === 'no-store' && str_contains($shell->body, 'noindex') && str_contains((string) ($shell->headers['Set-Cookie'] ?? ''), 'pixelite_bw=') && !preg_match('/<script(?![^>]*\bsrc=)[^>]*>|\son[a-z]+=|(src|href)="https?:\/\//i', $shell->body));
check('the builder page tells the visitor the workspace is temporary and carries the workspace-bound CSRF token, API root and upload URL', str_contains($shell->body, 'data-anonymous="1"') && str_contains($shell->body, 'data-api="/builder/w"') && str_contains($shell->body, 'data-upload="/builder/w/media"') && str_contains($shell->body, 'data-csrf="' . $A['csrf'] . '"') && str_contains($shell->body, 'deleted after 24 h') && !str_contains($shell->body, $A['secret']));
$site = $jv($vcall($A, 'GET', '/builder/w/site'));
check('anonymous: site JSON works (temporary=true, one starter page)', $site['ok'] === true && $site['temporary'] === true && $site['pages'][0]['slug'] === 'index' && $site['version'] === 1);
$cv = $vcall($A, 'GET', '/builder/w/pages/index/canvas');
check('anonymous: the canvas shows the starter page, sandboxed and script-free (same CSP as for customers)', $cv->status === 200 && str_contains($cv->body, 'Headline goes here') && str_starts_with((string) $cv->headers['Content-Security-Policy'], 'sandbox allow-same-origin;') && str_contains((string) $cv->headers['Content-Security-Policy'], "script-src 'none'") && !preg_match('/<script/i', $cv->body));
$save = fn(array $v, array $over = [], string $slug = 'index', ?string $csrf = null) => $vcall($v, 'POST', "/builder/w/pages/$slug", $over + ['base_version' => (string) SiteStore::load($v['key'])['version'], 'title' => 'Mine', 'html' => '<p id="mine">MARKER-A</p>', 'css' => 'p{color:#ff0000}'], null, [], $csrf);
$r = $save($A);
check('anonymous: saving a draft works, returns the sanitised page and a new version, and really persists on disk (not just a success message)', $r->status === 200 && $jv($r)['ok'] === true && $jv($r)['version'] === 2 && str_contains((string) file_get_contents("$BD/{$A['key']}/editor/builder/site.json"), 'MARKER-A') && SiteStore::load($A['key'])['pages']['index']['title'] === 'Mine');
check('anonymous: reloading shows the saved draft (canvas and site JSON)', str_contains($vcall($A, 'GET', '/builder/w/pages/index/canvas')->body, 'MARKER-A') && $jv($vcall($A, 'GET', '/builder/w/site'))['version'] === 2 && $jv($vcall($A, 'GET', '/builder/w/site'))['pages'][0]['title'] === 'Mine');
$pv = $vcall($A, 'GET', '/builder/w/pages/index/preview');
check('anonymous: the preview displays the saved draft (no editor helper css), sandboxed like the canvas', $pv->status === 200 && str_contains($pv->body, 'MARKER-A') && str_contains($pv->body, 'color:#ff0000') && !str_contains($pv->body, 'builder-canvas.css') && $pv->headers['Content-Security-Policy'] === $cv->headers['Content-Security-Policy']);
check('anonymous: pages can be created, saved and deleted; up/down the same quotas apply', (function () use ($vcall, $A, $jv) {
    $c = $vcall($A, 'POST', '/builder/w/pages', ['slug' => 'about', 'title' => 'About', 'base_version' => (string) SiteStore::load($A['key'])['version']]);
    if ($c->status !== 200) return false;
    $d = $vcall($A, 'POST', '/builder/w/pages/about/delete', ['base_version' => (string) SiteStore::load($A['key'])['version']]);
    return $d->status === 200 && !isset(SiteStore::load($A['key'])['pages']['about']);
})());

/* ---------------------------------------------------------------- 4. no workspace, no access; one visitor cannot reach another's */
check('without a cookie: the page redirects to the landing page and every API answers the same generic 401 (no workspace, no data)', (function () use ($vcall, $jv) {
    $none = ['cookie' => '', 'key' => '', 'secret' => '', 'csrf' => ''];
    $s = $vcall($none, 'GET', '/builder/w'); if ($s->status !== 302 || !str_ends_with((string) $s->headers['Location'], '/builder')) return false;
    foreach ([['GET', '/builder/w/site'], ['GET', '/builder/w/pages/index/canvas'], ['GET', '/builder/w/pages/index/preview'], ['GET', '/builder/w/media'], ['POST', '/builder/w/pages/index'], ['POST', '/builder/w/pages'], ['POST', '/builder/w/media'], ['GET', '/builder/w/media/m0123456789abcdef'], ['POST', '/builder/w/media/m0123456789abcdef/delete']] as [$m, $p]) {
        $r = $vcall($none, $m, $p, ['base_version' => '0', 'html' => 'x', 'css' => '', 'title' => 'x', 'slug' => 'zz']);
        if ($r->status !== 401 || str_contains($r->body, 'MARKER')) { fwrite(STDOUT, "     $m $p → {$r->status}\n"); return false; }
    }
    return true;
})());
$B = $visitor('198.51.100.20');
check('another visitor gets their OWN workspace: A\'s draft is not visible in B\'s canvas, site JSON or preview', $B['key'] !== $A['key'] && !str_contains($vcall($B, 'GET', '/builder/w/pages/index/canvas')->body, 'MARKER-A') && !str_contains($vcall($B, 'GET', '/builder/w/pages/index/preview')->body, 'MARKER-A') && $jv($vcall($B, 'GET', '/builder/w/site'))['pages'][0]['title'] === 'My first page');
$save($B, ['html' => '<p>MARKER-B</p>']);
check('B\'s saves land only in B\'s workspace (A is unchanged)', str_contains(SiteStore::load($B['key'])['pages']['index']['html'], 'MARKER-B') && !str_contains(json_encode(SiteStore::load($A['key'])), 'MARKER-B') && str_contains(json_encode(SiteStore::load($A['key'])), 'MARKER-A'));
check('knowing a workspace key is useless: the key with a wrong/foreign/malformed secret, or a key that does not exist, is "no workspace" (401), indistinguishable from each other', (function () use ($A, $B, $vcall) {
    $forged = [['cookie' => $A['key'] . '.' . $B['secret']], ['cookie' => $A['key'] . '.' . str_repeat('0', 32)], ['cookie' => 'w' . str_repeat('a', 32) . '.' . $A['secret']], ['cookie' => $A['key']], ['cookie' => $A['key'] . '.' . strtoupper($A['secret'])], ['cookie' => "../{$A['key']}." . $A['secret']], ['cookie' => $A['key'] . '.' . $A['secret'] . "\n"], ['cookie' => '..%2f..%2fprojects.' . $A['secret']]];
    $codes = [];
    foreach ($forged as $f) { $v = $f + ['key' => '', 'secret' => '', 'csrf' => AnonWorkspaces::csrf($A['key'], $A['secret'])]; $r = $vcall($v, 'GET', '/builder/w/site'); $codes[] = $r->status . ($r->body === '' ? '' : md5($r->body)); }
    return count(array_unique($codes)) === 1 && str_starts_with($codes[0], '401');
})());
check('the wrong visitor cannot write: B\'s cookie with A\'s CSRF token and A\'s cookie with B\'s token are refused (403), nothing is saved', (function () use ($A, $B, $save) {
    $v = SiteStore::load($A['key'])['version']; $w = SiteStore::load($B['key'])['version'];
    $x = $save($B, ['html' => '<p>HACK1</p>'], 'index', $A['csrf']); $y = $save($A, ['html' => '<p>HACK2</p>'], 'index', $B['csrf']);
    return $x->status === 403 && $y->status === 403 && SiteStore::load($A['key'])['version'] === $v && SiteStore::load($B['key'])['version'] === $w;
})());
check('cannot cross into customer data: the authenticated builder, editor, draft, media and admin routes refuse a visitor with a valid workspace cookie', (function () use ($A, $vcall, $PA, $BA) {
    foreach ([['GET', '/editor/projects/' . $BA['id'] . '/builder'], ['GET', '/editor/projects/' . $BA['id'] . '/builder/site'], ['GET', '/editor/projects/' . $BA['id'] . '/builder/pages/index/canvas'], ['GET', '/editor/projects/' . $PA['id']], ['GET', '/editor/projects/' . $PA['id'] . '/draft'], ['POST', '/editor/projects/' . $BA['id'] . '/builder/pages/index'], ['POST', '/editor/projects/' . $PA['id'] . '/media'], ['GET', '/admin'], ['GET', '/en/account']] as [$m, $p]) {
        $r = $vcall($A, $m, $p, ['base_version' => '0', 'html' => 'x', 'css' => '', 'title' => 'x']);
        if (!in_array($r->status, [302, 401], true) || str_contains($r->body, 'Builder A') || str_contains($r->body, 'A site')) { fwrite(STDOUT, "     $m $p → {$r->status}\n"); return false; }
    }
    return true;
})());
check('a customer project id is never accepted from the browser: ?id=, project/uid/path fields in the body or headers change nothing', (function () use ($A, $vcall, $PA, $jv) {
    $r = $vcall($A, 'POST', '/builder/w/pages/index', ['base_version' => (string) SiteStore::load($A['key'])['version'], 'html' => '<p>XMARK</p>', 'css' => '', 'title' => 'x', 'project' => (string) $PA['id'], 'uid' => $PA['uid'], 'customer_id' => '1', 'path' => '../../projects/' . $PA['uid']]);
    return $r->status === 200 && !is_dir(Pixelite\Editor\DraftStore::dir($PA['uid']) . '/builder') && str_contains(json_encode(SiteStore::load($A['key'])), 'XMARK');
})());
$save($A);   // restore MARKER-A

/* ---------------------------------------------------------------- 5. CSRF */
check('mutations without a valid token / without X-Requested-With are rejected (403) and change nothing: save, create page, delete page, upload, media delete', (function () use ($A, $vcall, $XHR) {
    $before = SiteStore::load($A['key'])['version'];
    $bad = [['', $XHR], ['wrong', $XHR], [$A['csrf'], []], [$A['csrf'], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']]];
    foreach ($bad as [$tok, $hdr]) {
        foreach ([['/builder/w/pages/index', ['base_version' => (string) $before, 'html' => '<p>NOPE</p>', 'css' => '', 'title' => 'x']], ['/builder/w/pages', ['slug' => 'nope', 'title' => 'x', 'base_version' => (string) $before]], ['/builder/w/pages/index/delete', ['base_version' => (string) $before]], ['/builder/w/media/m0123456789abcdef/delete', []]] as [$p, $post]) {
            $r = $vcall($A, 'POST', $p, $post + ['_csrf' => $tok], $hdr);
            if ($r->status !== 403) { fwrite(STDOUT, "     $p tok='$tok' → {$r->status}\n"); return false; }
        }
    }
    return SiteStore::load($A['key'])['version'] === $before && !str_contains(json_encode(SiteStore::load($A['key'])), 'NOPE');
})());
check('save: invalid base_version → 400, stale version → 409, oversize → 413, unknown page → 422', $save($A, ['base_version' => 'x'])->status === 400 && $save($A, ['base_version' => '1'])->status === 409 && $save($A, ['html' => str_repeat('a', 300001)])->status === 413 && $save($A, [], 'nope')->status === 422);

/* ---------------------------------------------------------------- 6. hostile content stays inert */
$evil = '<script>parent.__x=1</script><img src=x onerror="alert(1)"><a href="javascript:alert(1)" onclick="x()">x</a><iframe srcdoc="<script>1</script>"></iframe><svg onload=alert(1)></svg><form action="https://evil.example"><input formaction="//evil"></form><div style="background:url(//evil.example/x);width:expression(alert(1))">ok-text</div><img src="https://evil.example/t.png"><img src="/editor/projects/1/media/m0123456789abcdef">';
$r = $save($A, ['html' => $evil, 'css' => '@import url(//evil.example/x.css);body{background:url(javascript:alert(1))}</style><script>1</script>p{color:red}']);
$stored = json_encode(SiteStore::load($A['key']));
check('hostile HTML/CSS/URLs/handlers from an anonymous visitor are sanitised exactly like a customer\'s: nothing executable or external is stored or rendered', $r->status === 200 && !preg_match('/onerror|onclick|onload|javascript:|<script|<svg|<iframe|srcdoc|formaction|evil\.example|expression\(|@import|\/editor\/projects\/1\/media/i', $stored) && str_contains($stored, 'ok-text')
    && !preg_match('/<script|onerror|evil\.example/i', $vcall($A, 'GET', '/builder/w/pages/index/canvas')->body . $vcall($A, 'GET', '/builder/w/pages/index/preview')->body) && count($jv($r)['notes']) >= 3);
$save($A);

/* ---------------------------------------------------------------- 7. uploads */
if (!Media::available()) {
    check('GD is available (run in the Docker image / CI with the gd extension to test uploads)', false);
} else {
    $u = $upload($A, $png()); $uj = $jv($u);
    check('anonymous upload: a PNG is accepted, re-encoded, stored inside the visitor\'s workspace only and served at /builder/w/media/<id>', $u->status === 200 && preg_match('#^/builder/w/media/m[a-f0-9]{16}$#', $uj['url']) === 1 && str_starts_with((string) Media::path($A['key'], $uj['id']), realpath("$BD/{$A['key']}") . '/editor/media/'));
    $served = $vcall($A, 'GET', $uj['url']);
    check('the visitor can fetch their image (image type, nosniff, sandbox CSP, no-store); another visitor and a cookie-less client get nothing', $served->status === 200 && $served->headers['Content-Type'] === 'image/jpeg' && ($served->headers['X-Content-Type-Options'] ?? '') === 'nosniff' && str_contains((string) $served->headers['Content-Security-Policy'], 'sandbox') && $vcall($B, 'GET', $uj['url'])->status === 404 && $vcall(['cookie' => '', 'key' => '', 'secret' => '', 'csrf' => ''], 'GET', $uj['url'])->status === 401);
    check('uploads cannot bypass validation: polyglots are re-encoded, SVG/GIF/HTML/PHP/empty/oversize/absurd-dimension files are refused', (function () use ($upload, $A, $png, $jv) {
        $p = $upload($A, $png() . "\n<?php system('id'); ?><script>1</script>"); $f = Media::path($A['key'], (string) ($jv($p)['id'] ?? '')); $ok = $p->status === 200 && $f !== null && !str_contains((string) file_get_contents($f), '<?php');
        $huge = $png(); $huge = substr($huge, 0, 16) . pack('N', 30000) . pack('N', 30000) . substr($huge, 24);
        return $ok && $upload($A, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>' . str_repeat(' ', 100))->status === 422 && $upload($A, 'GIF89a' . str_repeat("\0", 200))->status === 422 && $upload($A, '<html><script>1</script></html>' . str_repeat(' ', 100))->status === 422
            && $upload($A, '')->status === 422 && $upload($A, str_repeat('a', Media::MAX_UPLOAD_BYTES + 5))->status === 413 && $upload($A, $huge)->status === 422;
    })());
    check('uploads need CSRF + header and a real HTTP upload (a file PHP did not receive is refused)', (function () use ($A, $pr, $as_visitor, $png, $tmp, $XHR) {
        $f = "$tmp/pu-x"; file_put_contents($f, $png()); $files = ['image' => ['name' => 'a.png', 'tmp_name' => $f, 'size' => 100, 'error' => 0]]; $c = new Pixelite\Editor\Builder\Anon\PublicBuilderController(false); $v = new Pixelite\Editor\Builder\Anon\PublicBuilderController(true);
        $as_visitor($A['cookie']); $a = $c->uploadMedia($pr('POST', '/builder/w/media', ['_csrf' => 'bad'], $XHR, $files), [])->status;
        $as_visitor($A['cookie']); $b = $c->uploadMedia($pr('POST', '/builder/w/media', ['_csrf' => $A['csrf']], [], $files), [])->status;
        $as_visitor($A['cookie']); $d = $v->uploadMedia($pr('POST', '/builder/w/media', ['_csrf' => $A['csrf']], $XHR, $files), [])->status;
        return $a === 403 && $b === 403 && $d === 400;
    })());
    $list = $jv($vcall($A, 'GET', '/builder/w/media'));
    check('media list shows only the visitor\'s images; delete works, refuses images in use and unknown ids', (function () use ($A, $B, $vcall, $jv, $save, $list) {
        $id = $list['images'][0]['id']; if (count($list['images']) < 2 || $jv($vcall($B, 'GET', '/builder/w/media'))['images'] !== []) return false;
        $save($A, ['html' => '<img src="/builder/w/media/' . $id . '" alt="x">']);
        $used = $vcall($A, 'POST', "/builder/w/media/$id/delete")->status === 409;
        $other = $list['images'][1]['id']; $del = $vcall($A, 'POST', "/builder/w/media/$other/delete")->status === 200;
        $foreign = $vcall($B, 'POST', "/builder/w/media/$id/delete")->status === 404;
        $save($A); return $used && $del && $foreign && $vcall($A, 'POST', "/builder/w/media/$id/delete")->status === 200;
    })());
    check('another visitor\'s page cannot show my image: the sanitiser only accepts /builder/w/media/<id> that exists in the workspace being rendered, and the media route is per workspace', (function () use ($A, $B, $upload, $jv, $vcall, $save) {
        $id = $jv($upload($A, imagecreate(1, 1) ? (function () { $im = imagecreatetruecolor(30, 30); ob_start(); imagepng($im); return (string) ob_get_clean(); })() : ''))['id'];
        $save($B, ['html' => '<img src="/builder/w/media/' . $id . '">']);   // B references A's id: the URL looks valid, but it resolves per workspace
        return $vcall($B, 'GET', '/builder/w/media/' . $id)->status === 404;
    })());
}

/* ---------------------------------------------------------------- 8. rate limits and quotas */
check('quota: a temporary workspace holds at most 5 pages (the 6th is refused with a clear message)', (function () use ($A, $vcall, $jv) {
    for ($i = 1; $i <= 5; $i++) { $r = $vcall($A, 'POST', '/builder/w/pages', ['slug' => "q$i", 'title' => "Q$i", 'base_version' => (string) SiteStore::load($A['key'])['version']]); }
    $n = count(SiteStore::load($A['key'])['pages']);
    return $n === 5 && $r->status === 422 && str_contains($jv($r)['error'], '5 pages');
})());
check('quota: total page content is capped (a save that would exceed 400 KB is refused, the previous content stays)', (function () use ($A, $vcall, $jv, $save) {
    $big = '<p>' . str_repeat('x', 250000) . '</p>';
    $a = $save($A, ['html' => $big], 'index');
    $b = $save($A, ['html' => $big], 'q1');
    return $a->status === 200 && $b->status === 422 && str_contains($jv($b)['error'], 'full') && SiteStore::load($A['key'])['pages']['q1']['html'] === '';
})());
$save($A);
if (Media::available()) {
    check('quota: at most 6 images per workspace (the 7th is refused), enforced on the real storage', (function () use ($A, $upload, $png) {
        Database::pdo()->exec('DELETE FROM rate_limits');
        $n = count(Media::all($A['key'])); $last = 200;
        for ($i = $n; $i < 6; $i++) { $last = $upload($A, $png(20 + $i, 20))->status; }
            return count(Media::all($A['key'])) === 6 && $upload($A, $png(77, 77))->status === 422;
    })());
}
check('rate limit: saves per workspace (120 per 10 min) are rejected with 429 + Retry-After after the limit', (function () use ($visitor, $vcall, $jv) {
    $V = $visitor('198.51.100.30'); $last = null;
    for ($i = 0; $i < 125; $i++) { $last = $vcall($V, 'POST', '/builder/w/pages/index', ['base_version' => 'x', 'html' => '', 'css' => '', 'title' => 'x'], null, [], null, '198.51.100.31' . ($i % 3)); if ($last->status === 429) break; }
    return $last->status === 429 && isset($last->headers['Retry-After']) && $i >= 100 && $i <= 122;
})());
check('rate limit: creating workspaces is limited per IP (6 per hour, then 429 + Retry-After and no cookie); other IPs are not affected', (function () use ($pr, $ph, $as_visitor, $landingToken, $visitor) {
    $ip = '203.0.113.99'; $codes = [];
    for ($i = 0; $i < 8; $i++) { $tok = $landingToken($ip); $as_visitor(null); $r = $ph($pr('POST', '/en/builder', ['_start' => $tok, 'action' => 'start'], [], [], $ip)); $codes[] = $r->status; if ($r->status === 429) { $retry = $r->headers['Retry-After'] ?? ''; $cookie = $r->headers['Set-Cookie'] ?? null; break; } }
    return array_slice($codes, 0, 6) === [303, 303, 303, 303, 303, 303] && end($codes) === 429 && $retry === '3600' && $cookie === null && $visitor('203.0.113.98')['cookie'] !== '';
})());
check('global capacity: when the number of temporary workspaces reaches the cap, starting is refused (503, no cookie) – expired ones are cleaned first', (function () use ($pr, $ph, $as_visitor, $landingToken, $BD) {
    Env::set('BUILDER_MAX_WORKSPACES', (string) (count(AnonWorkspaces::all()) + 0));
    $tok = $landingToken('203.0.113.77'); $as_visitor(null);
    $r = $ph($pr('POST', '/en/builder', ['_start' => $tok, 'action' => 'start'], [], [], '203.0.113.77'));
    Env::set('BUILDER_MAX_WORKSPACES', '');
    return $r->status === 503 && !isset($r->headers['Set-Cookie']) && str_contains($r->body, 'busy');
})());
check('global storage cap: a full storage budget refuses new workspaces and uploads (503)', (function () use ($pr, $ph, $as_visitor, $landingToken, $A, $upload, $png) {
    Database::pdo()->exec('DELETE FROM rate_limits'); Env::set('BUILDER_MAX_BYTES', '1000'); @unlink(AnonWorkspaces::root() . '/.usage');
    $tok = $landingToken('203.0.113.78'); $as_visitor(null);
    $r = $ph($pr('POST', '/en/builder', ['_start' => $tok, 'action' => 'start'], [], [], '203.0.113.78'));
    $u = Media::available() ? $upload($A, $png(55, 55))->status : 503;
    Env::set('BUILDER_MAX_BYTES', ''); @unlink(AnonWorkspaces::root() . '/.usage');
    return $r->status === 503 && $u === 503;
})());
check('"start over" replaces only the workspace this browser owns (new workspace created first, the old one deleted); an existing workspace is continued by a plain start', (function () use ($visitor, $pr, $ph, $as_visitor, $cookieOf, $BD) {
    $V = $visitor('198.51.100.40'); $as_visitor($V['cookie']);
    $b = $ph($pr('GET', '/en/builder', [], [], [], '198.51.100.40'))->body; preg_match('/name="_start" value="([a-f0-9]{64})"/', $b, $m);
    $as_visitor($V['cookie']); $cont = $ph($pr('POST', '/en/builder', ['_start' => $m[1], 'action' => 'start'], [], [], '198.51.100.40'));
    $as_visitor($V['cookie']); $new = $ph($pr('POST', '/en/builder', ['_start' => $m[1], 'action' => 'new'], [], [], '198.51.100.40'));
    $nc = $cookieOf($new);
    return !isset($cont->headers['Set-Cookie']) && $cont->headers['Location'] === '/builder/w' && $nc !== null && $nc !== $V['cookie'] && !is_dir("$BD/{$V['key']}") && is_dir("$BD/" . explode('.', $nc)[0]);
})());

/* ---------------------------------------------------------------- 9. concurrency */
check('concurrent requests cannot corrupt a draft or beat the limits: 8 parallel saves with the same version → exactly one wins, the rest are 409, site.json stays valid', (function () use ($visitor, $tmp, $BD) {
    $V = $visitor('198.51.100.50'); $script = "$tmp/par-save.php";
    file_put_contents($script, '<?php require "' . Paths::root('src/bootstrap.php') . '"; use Pixelite\Editor\Builder\SiteStore; $i = (int) $argv[2]; $r = SiteStore::mutate($argv[1], 1, function (array $s) use ($i) { usleep(20000); $s["pages"]["index"]["html"] = "<p>writer $i</p>"; return $s; }); echo $r["ok"] ? "ok" : (isset($r["conflict"]) ? "conflict" : "fail");');
    $procs = []; $env = $_ENV + ['BUILDER_DIR' => $BD];
    for ($i = 0; $i < 8; $i++) { $procs[] = proc_open([PHP_BINARY, $script, $V['key'], (string) $i], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i], null, ['BUILDER_DIR' => $BD, 'PATH' => getenv('PATH') ?: '/usr/bin']); }
    $out = []; foreach ($procs as $i => $p) { $out[] = trim((string) stream_get_contents($pipes[$i][1])); proc_close($p); }
    $ok = count(array_filter($out, fn($o) => $o === 'ok')); $site = json_decode((string) file_get_contents("$BD/{$V['key']}/editor/builder/site.json"), true);
    return $ok === 1 && count(array_filter($out, fn($o) => $o === 'conflict')) === 7 && is_array($site) && $site['version'] === 2;
})());
if (Media::available()) {
    check('concurrent uploads cannot overshoot the image quota: 8 parallel uploads into a workspace limited to 3 images store exactly 3', (function () use ($visitor, $tmp, $BD, $png) {
        $V = $visitor('198.51.100.51'); $script = "$tmp/par-up.php"; $img = "$tmp/par.png"; file_put_contents($img, $png(60, 40));
        file_put_contents($script, '<?php require "' . Paths::root('src/bootstrap.php') . '"; $r = Pixelite\Editor\Media::ingest($argv[1], file_get_contents($argv[2]), ["files" => 3, "bytes" => 10000000]); echo $r["ok"] ? "ok" : "no";');
        $procs = []; $pipes = [];
        for ($i = 0; $i < 8; $i++) { $procs[] = proc_open([PHP_BINARY, $script, $V['key'], $img], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i], null, ['BUILDER_DIR' => $BD, 'PATH' => getenv('PATH') ?: '/usr/bin']); }
        $out = []; foreach ($procs as $i => $p) { $out[] = trim((string) stream_get_contents($pipes[$i][1])); proc_close($p); }
        return count(array_filter($out, fn($o) => $o === 'ok')) === 3 && count(Media::all($V['key'])) === 3;
    })());
}

/* ---------------------------------------------------------------- 10. expiry and cleanup */
check('expiry: a workspace unused for more than 24 h stops working at once (401, "expired"), even before cleanup has run; recent activity keeps it alive', (function () use ($visitor, $vcall, $BD, $jv) {
    $V = $visitor('198.51.100.60'); $old = time() - 86400 - 120;
    touch("$BD/{$V['key']}/.activity", time() - 3600);
    $alive = $vcall($V, 'GET', '/builder/w/site')->status === 200;
    touch("$BD/{$V['key']}/.activity", $old);
    $r = $vcall($V, 'GET', '/builder/w/site'); $page = $vcall($V, 'GET', '/builder/w');
    return $alive && $r->status === 401 && ($jv($r)['expired'] ?? false) === true && $page->status === 302;
})());
check('cleanup deletes ONLY eligible temporary workspaces (expired, with their images) – never fresh ones, non-workspace folders, symlinks, customer projects or customer media', (function () use ($visitor, $BD, $png, $PA, $BA, $tmp) {
    $fresh = $visitor('198.51.100.61'); $old1 = $visitor('198.51.100.62'); $old2 = $visitor('198.51.100.63');
    if (Media::available()) { Media::ingest($old1['key'], $png(50, 40)); Media::ingest($fresh['key'], $png(51, 40)); }
    $past = time() - 86400 - 600;
    foreach ([$old1, $old2] as $o) { touch("$BD/{$o['key']}/.activity", $past); }
    // half-created workspace (no meta, old)
    $partial = 'w' . bin2hex(random_bytes(16)); mkdir("$BD/$partial"); touch("$BD/$partial", $past); @unlink("$BD/$partial/.activity");
    // decoys that must survive: wrong-name directory, a file, a symlink with a workspace-like name pointing at customer data
    mkdir("$BD/not-a-workspace"); file_put_contents("$BD/not-a-workspace/keep.txt", 'x'); touch("$BD/not-a-workspace", $past); touch("$BD/not-a-workspace/keep.txt", $past);
    $target = "$tmp/symlink-target-" . bin2hex(random_bytes(3)); mkdir($target); file_put_contents("$target/precious.txt", 'precious');
    $link = 'w' . bin2hex(random_bytes(16)); symlink($target, "$BD/$link");
    // customer data: an old project draft with media + an old customer project folder
    $projectDraft = Pixelite\Editor\DraftStore::dir($PA['uid']); @mkdir($projectDraft . '/media', 0775, true); file_put_contents("$projectDraft/draft.json", '{"version":3}'); touch("$projectDraft/draft.json", $past); touch($projectDraft, $past);
    $builderSite = SiteStore::dir($BA['uid']); @mkdir($builderSite, 0775, true); file_put_contents("$builderSite/site.json", '{"version":9,"pages":{}}'); touch("$builderSite/site.json", $past);
    $r = AnonWorkspaces::cleanup();
    $ok = !is_dir("$BD/{$old1['key']}") && !is_dir("$BD/{$old2['key']}") && !is_dir("$BD/$partial") && is_dir("$BD/{$fresh['key']}") && is_file("$BD/not-a-workspace/keep.txt") && is_file("$target/precious.txt") && is_link("$BD/$link")
        && is_file("$projectDraft/draft.json") && is_file("$builderSite/site.json") && $r['removed'] >= 3;
    if (Media::available()) { $ok = $ok && count(Media::all($fresh['key'])) === 1 && glob("$BD/{$old1['key']}/*") === []; }
    @unlink("$BD/$link");
    return $ok;
})());
check('cleanup also removes an expired visitor\'s uploaded images, and runs opportunistically (throttled by a marker file) so hosting without cron is covered', (function () use ($visitor, $BD, $png) {
    $V = $visitor('198.51.100.64'); if (Media::available()) { Media::ingest($V['key'], $png(52, 40)); }
    touch("$BD/{$V['key']}/.activity", time() - 86400 - 60);
    @unlink("$BD/.cleanup"); AnonWorkspaces::maybeCleanup(); $gone = !is_dir("$BD/{$V['key']}");
    $W = $visitor('198.51.100.65'); touch("$BD/{$W['key']}/.activity", time() - 86400 - 60);
    AnonWorkspaces::maybeCleanup();   // within 15 minutes of the last run: skipped
    $kept = is_dir("$BD/{$W['key']}"); AnonWorkspaces::maybeCleanup(true);
    return $gone && $kept && !is_dir("$BD/{$W['key']}");
})());
check('bin/cleanup-builder.php is CLI-only and its dry run lists what would go without deleting', (function () use ($visitor, $BD, $tmp) {
    $V = $visitor('198.51.100.66'); touch("$BD/{$V['key']}/.activity", time() - 86400 - 60);
    $out = shell_exec('BUILDER_DIR=' . escapeshellarg($BD) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(Paths::root('bin/cleanup-builder.php')) . ' --dry-run 2>&1');
    $kept = is_dir("$BD/{$V['key']}"); AnonWorkspaces::cleanup();
    return $kept && str_contains((string) $out, 'would remove ' . $V['key']) && str_contains((string) file_get_contents(Paths::root('bin/cleanup-builder.php')), "PHP_SAPI !== 'cli'");
})());

/* ---------------------------------------------------------------- 11. feature switch, hosting prerequisites, regressions */
check('production default: with BUILDER_PUBLIC unset the public builder is ON (no EDITOR_ENABLED needed): the authenticated experimental editor stays a separate switch', (function () use ($pr, $ph) {
    $env = Env::get('EDITOR_ENABLED'); Env::set('EDITOR_ENABLED', ''); Env::set('BUILDER_PUBLIC', '');
    $land = $ph($pr('GET', '/en/builder'))->status === 200; $app = $ph($pr('GET', '/builder/w'))->status === 302; $cust = $ph($pr('GET', '/editor/projects/1/builder'))->status === 404;
    Env::set('EDITOR_ENABLED', $env); return $land && $app && $cust;
})());
check('BUILDER_PUBLIC=false switches the public builder off: landing and /builder/w are 404, the nav link and sitemap entry disappear', (function () use ($pr, $ph) {
    Env::set('BUILDER_PUBLIC', 'false');
    $a = $ph($pr('GET', '/en/builder'))->status === 404 && $ph($pr('GET', '/builder/w'))->status === 404 && $ph($pr('POST', '/en/builder', ['_start' => 'x']))->status === 404 && $ph($pr('GET', '/builder/w/site'))->status === 404;
    $b = !str_contains($ph($pr('GET', '/en/pricing'))->body, 'href="/en/builder"') && !str_contains($ph($pr('GET', '/sitemap.xml'))->body, '/en/builder<');
    Env::set('BUILDER_PUBLIC', ''); return $a && $b;
})());
check('hosting prerequisites are checked at run time: an unwritable storage folder or missing PHP DOM turns the landing page into a clear "unavailable" message and start is refused (503) – no half-working builder', (function () use ($pr, $ph, $as_visitor, $tmp, $BD) {
    $file = "$tmp/not-a-dir"; file_put_contents($file, 'x'); Env::set('BUILDER_DIR', $file);
    $l = $ph($pr('GET', '/en/builder')); $as_visitor(null); $s = $ph($pr('POST', '/en/builder', ['_start' => 'x', 'action' => 'start']));
    Env::set('BUILDER_DIR', $BD);
    return $l->status === 200 && str_contains($l->body, 'not available') && !str_contains($l->body, 'name="_start"') && $s->status === 503 && AnonWorkspaces::available() && HtmlSanitizer::available() && is_writable(AnonWorkspaces::root());
})());
check('the homepage and every important public route still load in all four languages with the builder enabled', (function () use ($pr, $ph, $as_visitor) {
    $bad = [];
    foreach (['en', 'cs', 'ru', 'uk'] as $lg) { foreach (['', 'pricing', 'process', 'contact', 'templates', 'templates/lumora', 'team', 'careers', 'privacy', 'cookies', 'terms', 'order', 'login', 'register', 'builder'] as $pg) { $as_visitor(null); $r = $ph($pr('GET', "/$lg/$pg")); if ($r->status !== 200) { $bad[] = "$lg/$pg:" . $r->status; } } }
    $as_visitor(null); foreach (['/robots.txt', '/sitemap.xml'] as $p) { if ($ph($pr('GET', $p))->status !== 200) { $bad[] = $p; } }
    return $bad === [] ? true : (fwrite(STDOUT, '     ' . implode(' ', $bad) . "\n") && false);
})());
check('no new public route exposes anything but the builder: every added route is /{lang}/builder or under /builder/w', (function () { $s = (string) file_get_contents(Paths::root('src/bootstrap.php')); preg_match_all("#\\\$router->add\\('(?:GET|POST)', '(/builder[^']*|/\\{lang\\}/builder)'#", $s, $m); foreach ($m[1] as $p) { if ($p !== '/{lang}/builder' && !str_starts_with($p, '/builder/w')) return false; } return count($m[1]) === 13; })());
check('licensing: Bootstrap and VvvebJs licences and modification notices ship with the vendored code; no AGPL Vvveb CMS files', (function () {
    $v = Paths::publicDir('assets/editor/vendor'); if (!is_file("$v/bootstrap/LICENSE") || !str_contains((string) file_get_contents("$v/bootstrap/LICENSE"), 'MIT') || !is_file("$v/vvvebjs/LICENSE")) return false;
    foreach (glob("$v/vvvebjs/*.js") as $f) { $s = (string) file_get_contents($f); if (!str_contains($s, 'MODIFIED BY PIXELITE') || !str_contains($s, 'Licensed under the Apache License')) { fwrite(STDOUT, "     " . basename($f) . "\n"); return false; } }
    return !is_dir("$v/vvveb") && str_contains((string) file_get_contents("$v/bootstrap/bootstrap.min.css"), 'Licensed under MIT');
})());
Env::set('BUILDER_DIR', '');
exec('rm -rf ' . escapeshellarg($BD));
$_COOKIE = [];
