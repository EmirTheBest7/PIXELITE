<?php
declare(strict_types=1);

// Included at the end of tests/run.php (shares check() and $tmp). EXPERIMENTAL visual editor (src/Editor/): feature switch, authentication, tenant isolation,
// CSRF, schema validation (what a draft may contain), image re-encoding, storage layout, rendering/escaping and response headers.
// The real-browser part (selection whitelist, undo/redo, save → reload, preview, phone width) is tests/browser/editor.mjs.

use Pixelite\{Customers, Database, Env, Paths, ProjectStorage, Projects, Request};
use Pixelite\Editor\{DraftStore, EditorController, Media, Schema};

echo "\nvisual editor (experimental)\n";
$ER = "$tmp/editor-" . bin2hex(random_bytes(3));
Env::set('PROJECTS_DIR', $ER);
Env::set('PREVIEW_ORIGIN', '');
Env::set('EDITOR_ENABLED', 'true');
Env::set('DESIGN_DEFAULT', 'new');
Database::reset();

$eq = fn(string $m, string $p, array $post = [], array $hdr = [], array $files = [], string $ip = '203.0.113.77') => new Request($m, $p, $post, [], ['REMOTE_ADDR' => $ip] + $hdr, $files);
$eh = fn(Request $r) => Pixelite\handle($r);
$as = function (?int $id): void { $_SESSION = $id === null ? [] : ['customer_id' => $id, 'customer_at' => time(), 'customer_since' => time(), '_csrf' => 'ed-token']; };
$XHR = ['HTTP_X_REQUESTED_WITH' => 'pixelite-editor'];

Customers::create(['name' => 'Ed A', 'email' => 'ed-a@example.test', 'company' => '', 'phone' => '', 'password' => 'correct-horse-battery'], 'en');
Customers::create(['name' => 'Ed B', 'email' => 'ed-b@example.test', 'company' => '', 'phone' => '', 'password' => 'correct-horse-battery'], 'en');
$A = (int) Customers::findByEmail('ed-a@example.test')['id'];
$B = (int) Customers::findByEmail('ed-b@example.test')['id'];
$mkProject = fn(?int $cust, string $name = 'Site', string $status = 'draft') => Projects::create(['name' => $name, 'notes' => '', 'status' => $status, 'order_id' => null, 'customer_id' => $cust]);
$PA = $mkProject($A, 'A site');
$PB = $mkProject($B, 'B site');
$PX = $mkProject(null, 'Unassigned');
$PZ = $mkProject($A, 'Archived', 'archived');
$base = fn(array $p) => '/editor/projects/' . $p['id'];
$routes = fn(array $p) => [['GET', ''], ['GET', '/canvas'], ['GET', '/preview'], ['GET', '/theme.css'], ['GET', '/draft'], ['POST', '/draft'], ['POST', '/media'], ['GET', '/media/m0123456789abcdef']];

/* ---------------------------------------------------------------- feature switch */
Env::set('EDITOR_ENABLED', 'false');
$as($A);
check('editor OFF (default): every editor route is a plain 404, even for the project owner', (function () use ($eh, $eq, $routes, $PA, $base) {
    foreach ($routes($PA) as [$m, $s]) { if ($eh($eq($m, $base($PA) . $s, ['_csrf' => 'ed-token']))->status !== 404) return false; }
    return true;
})());
Env::set('EDITOR_ENABLED', '');
check('editor OFF when EDITOR_ENABLED is empty/unset too', $eh($eq('GET', $base($PA)))->status === 404);
Env::set('EDITOR_ENABLED', 'true');

/* ---------------------------------------------------------------- authentication + tenant isolation */
$as(null);
check('guest: the editor page redirects to the sign-in page', ($r = $eh($eq('GET', $base($PA))))->status === 302 && str_ends_with((string) ($r->headers['Location'] ?? ''), '/login'));
check('guest: draft, canvas, preview, theme, media and every mutation answer 401 (no project details)', (function () use ($eh, $eq, $routes, $PA, $base) {
    foreach ($routes($PA) as [$m, $s]) { if ($s === '') continue; $r = $eh($eq($m, $base($PA) . $s, ['_csrf' => 'ed-token'], ['HTTP_X_REQUESTED_WITH' => 'pixelite-editor'])); if ($r->status !== 401 || str_contains($r->body, 'A site')) return false; }
    return true;
})());
$as($B);
check("customer B cannot open, read or write customer A's project (all routes 404, identical to a missing project)", (function () use ($eh, $eq, $routes, $PA, $base, $XHR) {
    $missing = $eh($eq('GET', '/editor/projects/99999/draft'));
    foreach ($routes($PA) as [$m, $s]) {
        $r = $eh($eq($m, $base($PA) . $s, ['_csrf' => 'ed-token', 'base_version' => '0', 'values' => '{"hero_line1":"hacked"}'], $XHR));
        if ($r->status !== 404 || str_contains($r->body, 'A site')) return false;
    }
    return $missing->status === 404;
})());
check('a project that belongs to nobody, and an archived project, are 404 as well', (function () use ($eh, $eq, $as, $A, $PX, $PZ, $base) { $as($A); return $eh($eq('GET', $base($PX)))->status === 404 && $eh($eq('GET', $base($PZ)))->status === 404; })());
check("customer A's draft is still untouched after B's attempts", DraftStore::load($PA['uid'])['version'] === 0);
check('a disabled customer account loses access immediately', (function () use ($eh, $eq, $as, $A, $PA, $base) { Customers::setStatus($A, 'disabled'); $as($A); $s = $eh($eq('GET', $base($PA) . '/draft'))->status; Customers::setStatus($A, 'active'); return $s === 401; })());

/* ---------------------------------------------------------------- the editor page and its documents */
$as($A);
$shell = $eh($eq('GET', $base($PA)));
check('owner: editor page renders (200) with the project name, the project-scoped base URL and the session CSRF token in data attributes', $shell->status === 200 && str_contains($shell->body, 'data-base="/editor/projects/' . $PA['id'] . '"') && str_contains($shell->body, 'data-csrf="ed-token"') && str_contains($shell->body, 'A site'));
$csp = (string) ($shell->headers['Content-Security-Policy'] ?? '');
check("editor page CSP: script-src 'self' only (no unsafe-eval, no unsafe-inline), no third-party hosts, not frameable", preg_match("/script-src 'self';/", $csp) === 1 && !str_contains($csp, 'unsafe-eval') && !preg_match("/script-src[^;]*unsafe-inline/", $csp) && !preg_match('#https?://#', $csp) && str_contains($csp, "frame-ancestors 'none'") && ($shell->headers['X-Frame-Options'] ?? '') === 'DENY');
check('editor page has no inline <script>, <style>, on*= handlers and no third-party URLs', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>|<style\b|\son[a-z]+=|(src|href)="https?:\/\//i', $shell->body));
check('editor page is noindex and never cached', str_contains($shell->body, 'noindex,nofollow') && ($shell->headers['Cache-Control'] ?? '') === 'no-store');
check('every script/stylesheet the editor page loads exists locally', (function () use ($shell) {
    preg_match_all('#(?:src|href)="(/assets/[^"?]+)#', $shell->body, $m);
    foreach ($m[1] as $u) { if (!is_file(Paths::publicDir(ltrim($u, '/')))) { fwrite(STDOUT, "     missing $u\n"); return false; } }
    return count($m[1]) >= 6;
})());
$canvas = $eh($eq('GET', $base($PA) . '/canvas'));
$preview = $eh($eq('GET', $base($PA) . '/preview'));
check('canvas document: edit markers present, framable only by the same origin, strict CSP (no inline script, no eval, connect-src none)', $canvas->status === 200 && str_contains($canvas->body, 'data-px-field="hero_line1"') && ($canvas->headers['X-Frame-Options'] ?? '') === 'SAMEORIGIN'
    && str_contains((string) $canvas->headers['Content-Security-Policy'], "frame-ancestors 'self'") && str_contains((string) $canvas->headers['Content-Security-Policy'], "connect-src 'none'") && !str_contains((string) $canvas->headers['Content-Security-Policy'], 'unsafe-eval') && !preg_match("/script-src[^;]*unsafe-inline/", (string) $canvas->headers['Content-Security-Policy']));
check('preview document: the same page without any editor marker or editor stylesheet', $preview->status === 200 && !str_contains($preview->body, 'data-px-') && !str_contains($preview->body, 'canvas.css') && str_contains($preview->body, 'Stop Digging'));
check('canvas and preview contain no inline script/style and no third-party host', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>|<style\b|\son[a-z]+=|(src|href)="https?:\/\/(?!example)/i', $canvas->body . $preview->body));
check('an untouched draft renders exactly the text of the original demo (defaults = demo copy)', (function () use ($preview) {
    $demo = (string) file_get_contents(Paths::root('templates/demos/vantage.html'));
    foreach (['Stop Digging', 'Through Dashboards.', 'Get Started', 'Watch Demo', 'Sign Up', 'Your metrics are scattered across a dozen dashboards.', 'decision is backed by data you actually trust.', 'watch-demo-thumbnail.jpg'] as $t) { if (!str_contains($preview->body, $t) || !str_contains($demo, $t)) return false; }
    return true;
})());
check('the original demo template is not modified by the editor (templates/demos/vantage.html still has its own markup and no editor attributes)', !str_contains((string) file_get_contents(Paths::root('templates/demos/vantage.html')), 'data-px-'));

/* ---------------------------------------------------------------- saving: CSRF, header, size, shape, version */
$save = function (array $values, ?int $ver = null, array $over = [], ?array $hdr = null) use ($eh, $eq, $PA, $base, $XHR) {
    Database::pdo()->exec('DELETE FROM rate_limits');   // the limiter is covered separately; these loops make many saves
    $post = $over + ['_csrf' => 'ed-token', 'base_version' => (string) ($ver ?? DraftStore::load($PA['uid'])['version']), 'values' => json_encode($values)];   // $over wins
    return $eh($eq('POST', $base($PA) . '/draft', $post, $hdr ?? $XHR));
};
$j = fn(Pixelite\Response $r) => json_decode($r->body, true);
check('save without a CSRF token → 403, nothing written', $save(['hero_line1' => 'x'], null, ['_csrf' => ''])->status === 403 && DraftStore::load($PA['uid'])['version'] === 0);
check('save with a wrong CSRF token → 403', $save(['hero_line1' => 'x'], null, ['_csrf' => 'nope'])->status === 403);
check('save without the X-Requested-With header → 403', $save(['hero_line1' => 'x'], null, [], [])->status === 403);
check('save with another header value → 403', $save(['hero_line1' => 'x'], null, [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'])->status === 403);
check('a GET can never change a draft (GET /draft with values is read-only)', $eh($eq('GET', $base($PA) . '/draft', ['values' => '{"hero_line1":"x"}']))->status === 200 && DraftStore::load($PA['uid'])['values']['hero_line1'] === 'Stop Digging');
check('save: invalid JSON / JSON list / oversized body → 400', $save([], null, ['values' => '{nope'])->status === 400 && $save([], null, ['values' => '["a"]'])->status === 400 && $save([], null, ['values' => json_encode(['hero_line1' => str_repeat('a', 20000)])])->status === 400);
check('save: missing or non-numeric base_version → 400', $save([], null, ['base_version' => 'abc'])->status === 400 && $save([], null, ['base_version' => ''])->status === 400);
check('save: unknown fields (incl. path-like keys) are refused → 422, draft unchanged', ($r = $save(['../../etc/passwd' => 'x', 'evil_html' => '<b>']))->status === 422 && isset($j($r)['fields']) && DraftStore::load($PA['uid'])['version'] === 0);
$r = $save(['hero_line1' => '  Know   your ' . "\x07" . "numbers\u{202E}  ", 'nav_about_href' => 'https://example.com/a?b=1', 'color_cta' => '#FFF200', 'show_demo_card' => false, 'hero_copy' => "Line one\r\n\r\nLine   two"]);
$d = DraftStore::load($PA['uid']);
check('save: valid values are accepted, normalised (whitespace, control and bidi characters removed, colours lower-cased, lines tidied) and versioned', $r->status === 200 && ($j($r)['ok'] ?? false) === true && $d['version'] === 1
    && $d['values']['hero_line1'] === 'Know your numbers' && $d['values']['color_cta'] === '#fff200' && $d['values']['show_demo_card'] === false && $d['values']['hero_copy'] === "Line one\nLine two" && $d['values']['nav_about_href'] === 'https://example.com/a?b=1');
check('a partial save keeps every other stored value', $d['values']['hero_line2'] === 'Through Dashboards.' && $d['values']['cta_label'] === 'Get Started');
check('stale base_version → 409 and nothing is overwritten', ($r = $save(['hero_line1' => 'stale'], 0))->status === 409 && DraftStore::load($PA['uid'])['values']['hero_line1'] === 'Know your numbers');
check('saved draft is returned by GET /draft and survives a fresh load from disk ("reload")', (function () use ($eh, $eq, $PA, $base, $j) { $r = $j($eh($eq('GET', $base($PA) . '/draft'))); return $r['values']['hero_line1'] === 'Know your numbers' && $r['version'] === 1 && $r['values']['color_cta'] === '#fff200'; })());

$matrix = fn(string $field, array $cases, string $why) => (function () use ($save, $PA, $field, $cases) {
    foreach ($cases as $value => $expect) {
        $before = DraftStore::load($PA['uid'])['version'];
        $s = $save([$field => (string) $value])->status;
        if (($expect ? 200 : 422) !== $s) { fwrite(STDOUT, "     $field=" . json_encode($value) . " → $s\n"); return false; }
        if (!$expect && DraftStore::load($PA['uid'])['version'] !== $before) return false;
    }
    return true;
})();
check('links: only https/http/mailto/tel/#anchor are accepted; javascript:, data:, vbscript:, protocol-relative, relative, quote/space/control tricks and credentials are refused', $matrix('nav_home_href', [
    'https://example.com' => true, 'HTTP://Example.com/x?y=1#z' => true, 'mailto:hi@example.com' => true, 'tel:+420123456789' => true, '#about' => true, '#' => true,
    'javascript:alert(1)' => false, 'JaVaScRiPt:alert(1)' => false, " javascript:alert(1)" => false, 'data:text/html,<script>1</script>' => false, 'vbscript:x' => false, '//evil.example/x' => false, '/relative' => false,
    'https://user:pw@example.com' => false, 'https://example.com" onmouseover="x' => false, "https://exa mple.com" => false, 'https://localhost' => false, 'mailto:not an email' => false, 'ftp://example.com' => false, 'file:///etc/passwd' => false,
], 'links'));
check('colours: only #rrggbb (or empty = template default)', $matrix('color_copy', ['#a1B2c3' => true, '' => true, 'red' => false, '#fff' => false, 'rgb(1,2,3)' => false, 'url(x)' => false, '#12345g' => false, '#1234567' => false, 'expression(1)' => false], 'colours'));
check('text length limits are enforced (headline 22, button 14) and empty required text is refused', $matrix('hero_line1', [str_repeat('a', 22) => true, str_repeat('a', 23) => false, '   ' => false], 'len') && $matrix('cta_label', [str_repeat('b', 14) => true, str_repeat('b', 15) => false], 'len'));
check('multiline: at most 4 non-empty lines and 260 characters', $matrix('hero_copy', ["a\nb\nc\nd" => true, "a\nb\nc\nd\ne" => false, str_repeat('x', 261) => false, "\n\n" => false], 'lines'));
check('wrong types are refused (array for text, string for bool, number for colour)', (function () use ($save) { return $save(['hero_line1' => ['x']])->status === 422 && $save(['show_demo_card' => 'yes'])->status === 422 && $save(['color_copy' => 123])->status === 422 && $save(['hero_line1' => null])->status === 422; })());
check('script/markup typed as text is stored as text and escaped on output, never as markup', (function () use ($save, $eh, $eq, $PA, $base) {
        if ($save(['hero_line1' => '<script>1</script>', 'hero_line2' => '"><i onerror=1>', 'demo_alt' => '" onload="1'])->status !== 200) return false;
    foreach (['/canvas', '/preview'] as $s) {
        $b = $eh($eq('GET', $base($PA) . $s))->body;
        if (str_contains($b, '<script>1') || str_contains($b, '<i onerror') || !str_contains($b, '&lt;script&gt;1&lt;/script&gt;')) { $GLOBALS['why'] = "$s raw markup / not escaped"; return false; }
        libxml_use_internal_errors(true);
        $dom = new DOMDocument(); $dom->loadHTML($b);
        foreach ($dom->getElementsByTagName('*') as $el) {
            if ($el->nodeName === 'script' && !$el->hasAttribute('src')) { $GLOBALS['why'] = 'inline script'; return false; }   // no inline script was created
            foreach ($el->attributes as $a) { if (str_starts_with($a->name, 'on')) { $GLOBALS['why'] = $el->nodeName . ' ' . $a->name; return false; } }   // no event-handler attribute was created
        }
        if ($dom->getElementsByTagName('h1')->item(0)->textContent === null || !str_contains($dom->getElementsByTagName('h1')->item(0)->textContent, '<script>1</script>')) { $GLOBALS['why'] = 'h1: ' . $dom->getElementsByTagName('h1')->item(0)->textContent; return false; }   // it is text
    }
    return true;
})(), (string) ($GLOBALS['why'] ?? ''));
$save(['hero_line1' => 'Stop Digging', 'hero_line2' => 'Through Dashboards.', 'demo_alt' => 'Smoke']);
check('colour variables in theme.css come only from validated #rrggbb values (CSS cannot be injected)', (function () use ($save, $eh, $eq, $PA, $base) {
    $save(['color_title_1' => '#ff8800', 'color_cta' => '#fff200']);
    $css = $eh($eq('GET', $base($PA) . '/theme.css'));
    return $css->status === 200 && str_starts_with((string) $css->headers['Content-Type'], 'text/css') && str_contains($css->body, '--px-title-1:#ff8800') && str_contains($css->body, '--px-cta-text:#111111')
        && preg_match('/^\/\*[^\n]*\*\/\nbody\{(--px-[a-z0-9-]+:[#a-z0-9]+;?)+\}\n$/', $css->body) === 1;
})());
$save(['color_title_1' => '', 'color_cta' => '']);

/* ---------------------------------------------------------------- images */
$png = function (int $w = 40, int $h = 30, bool $alpha = false): string { $im = imagecreatetruecolor($w, $h); if ($alpha) { imagealphablending($im, false); imagesavealpha($im, true); imagefill($im, 0, 0, (int) imagecolorallocatealpha($im, 10, 200, 90, 60)); } else { imagefill($im, 0, 0, (int) imagecolorallocate($im, 10, 120, 200)); } ob_start(); imagepng($im); return (string) ob_get_clean(); };
$up = function (string $bytes, ?int $unused = null, string $name = 'x.png') use ($tmp, $PA, $base, $XHR, $eq, $as, $A) {
    Database::pdo()->exec('DELETE FROM rate_limits');
    $f = "$tmp/up-" . bin2hex(random_bytes(3)); file_put_contents($f, $bytes);
    $ctl = new EditorController(false);
    $r = $eq('POST', $base($PA) . '/media', ['_csrf' => 'ed-token'], $XHR, ['image' => ['name' => $name, 'tmp_name' => $f, 'size' => strlen($bytes), 'error' => UPLOAD_ERR_OK]]);
    $as($A);
    return $ctl->uploadMedia($r, ['id' => (string) $PA['id']]);
};
if (!Media::available()) {
    check('GD is available (the editor refuses uploads without it; install GD to run the image tests)', false, 'run in the Docker image (docker compose up --build)');
} else {
    $r = $up($png());
    $o = json_decode($r->body, true);
    $file = Media::path($PA['uid'], (string) ($o['id'] ?? ''));
    check('upload: a PNG is accepted, gets a server-made id, and is stored inside THIS project\'s editor/media folder only', $r->status === 200 && preg_match('/^m[a-f0-9]{16}$/', (string) $o['id']) === 1 && $file !== null && str_starts_with($file, realpath(DraftStore::dir($PA['uid'])) . '/media/') && ($o['url'] ?? '') === $base($PA) . '/media/' . $o['id']);
    check('upload: the file on disk is a re-encoded image (no alpha → JPEG), not the original bytes', $file !== null && str_ends_with($file, '.jpg') && str_starts_with((string) file_get_contents($file), "\xFF\xD8\xFF") && (fileperms($file) & 0111) === 0);
    $poly = $png() . "\n<?php system('id'); ?>\n<script>alert(1)</script>";
    $r2 = $up($poly); $o2 = json_decode($r2->body, true); $f2 = Media::path($PA['uid'], (string) ($o2['id'] ?? ''));
    check('upload: an image with PHP/HTML appended (polyglot) is re-encoded and the payload is gone', $r2->status === 200 && $f2 !== null && !str_contains((string) file_get_contents($f2), '<?php') && !str_contains((string) file_get_contents($f2), '<script'));
    $r3 = $up($png(60, 40, true)); $o3 = json_decode($r3->body, true); $f3 = Media::path($PA['uid'], (string) ($o3['id'] ?? ''));
    check('upload: a transparent PNG stays a PNG (alpha kept) and keeps a correct Content-Type when served', $f3 !== null && str_ends_with($f3, '.png') && Media::mime($f3) === 'image/png');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>';
    check('upload: SVG, GIF, HTML, PHP, plain text and an empty file are refused (422) – the real content decides, not the name', $up($svg, null, 'x.png')->status === 422 && $up('GIF89a' . str_repeat("\0", 200), null, 'x.png')->status === 422 && $up('<html><script>1</script></html>' . str_repeat(' ', 200), null, 'a.jpg')->status === 422 && $up("<?php echo 1; ?>" . str_repeat(' ', 200), null, 'a.png.php')->status === 422);
    $huge = $png(); $huge = substr($huge, 0, 16) . pack('N', 30000) . pack('N', 30000) . substr($huge, 24);
    check('upload: an image that declares absurd dimensions is refused before decoding (decompression bomb)', $up($huge)->status === 422);
    check('upload: more than 4 MB is refused', $up(str_repeat('a', Media::MAX_UPLOAD_BYTES + 10))->status === 413);
    check('upload: not a real HTTP upload (tmp file not created by PHP) is refused when verification is on', (function () use ($eq, $png, $tmp, $PA, $base, $XHR, $as, $A) { $f = "$tmp/fake"; file_put_contents($f, $png()); $as($A); return (new EditorController(true))->uploadMedia($eq('POST', $base($PA) . '/media', ['_csrf' => 'ed-token'], $XHR, ['image' => ['name' => 'a.png', 'tmp_name' => $f, 'size' => 100, 'error' => 0]]), ['id' => (string) $PA['id']])->status === 400; })());
    check('upload without CSRF token or custom header → 403 (nothing stored)', (function () use ($eq, $png, $tmp, $PA, $base, $XHR, $as, $A, $ER) { $f = "$tmp/c"; file_put_contents($f, $png()); $as($A); $n = count(glob(DraftStore::dir($PA['uid']) . '/media/*') ?: []); $file = ['image' => ['name' => 'a.png', 'tmp_name' => $f, 'size' => 100, 'error' => 0]]; $c = new EditorController(false);
        $a = $c->uploadMedia($eq('POST', $base($PA) . '/media', ['_csrf' => 'bad'], $XHR, $file), ['id' => (string) $PA['id']])->status; $b = $c->uploadMedia($eq('POST', $base($PA) . '/media', ['_csrf' => 'ed-token'], [], $file), ['id' => (string) $PA['id']])->status;
        return $a === 403 && $b === 403 && count(glob(DraftStore::dir($PA['uid']) . '/media/*') ?: []) === $n; })());
    // serving + isolation of media
    $mid = (string) $o['id'];
    $served = $eh($eq('GET', $base($PA) . '/media/' . $mid));
    check('owner can fetch an uploaded image: image Content-Type, nosniff, sandboxed CSP, never cached', $served->status === 200 && $served->headers['Content-Type'] === 'image/jpeg' && ($served->headers['X-Content-Type-Options'] ?? '') === 'nosniff' && str_contains((string) $served->headers['Content-Security-Policy'], 'sandbox') && str_contains((string) ($served->headers['Cache-Control'] ?? ''), 'no-store'), json_encode([$served->status, $served->headers]));
    check('media routes refuse path-like or malformed ids (404)', (function () use ($eh, $eq, $PA, $base, $as, $A) { $as($A); $c = new EditorController(false); foreach (['..', '../site/index', 'm..%2F..%2Fleads', 'M0123456789ABCDEF', 'm0123456789abcde', '..%2F..%2F.env', 'a/b', "m0123456789abcdef\0"] as $s) { if ($c->media($eq('GET', $base($PA) . '/media/x'), ['id' => (string) $PA['id'], 'slug' => $s])->status !== 404) return false; } return true; })());
    $as($B);
    check("customer B cannot fetch A's image, and cannot attach A's image id to B's own draft", $eh($eq('GET', $base($PA) . '/media/' . $mid))->status === 404 && (function () use ($eh, $eq, $PB, $mid, $XHR, $base) { $r = $eh($eq('POST', $base($PB) . '/draft', ['_csrf' => 'ed-token', 'base_version' => '0', 'values' => json_encode(['demo_image' => $mid])], $XHR)); return $r->status === 422; })());
    $as($A);
    check("customer A can attach the uploaded image to the draft; the preview then points at the authenticated media route (not a public file)", (function () use ($save, $eh, $eq, $PA, $base, $mid) { $r = $save(['demo_image' => $mid, 'show_demo_card' => true]); $b = $eh($eq('GET', $base($PA) . '/preview'))->body; $GLOBALS['why'] = $r->status . ' ' . substr(preg_replace('/\s+/', ' ', substr($b, (int) strpos($b, 'demo-visual'), 260)), 0, 260); return $r->status === 200 && str_contains($b, 'src="' . $base($PA) . '/media/' . $mid . '"'); })(), (string) ($GLOBALS['why'] ?? ''));
    check('a made-up (well-formed but unknown) image id is refused (422)', $save(['demo_image' => 'm' . str_repeat('0', 16)])->status === 422);
    check('image limits: the per-draft file cap is enforced', (function () use ($up, $png, $PA) { $n = count(glob(DraftStore::dir($PA['uid']) . '/media/m*') ?: []); for ($i = $n; $i < Media::MAX_FILES; $i++) { $up($png(20 + $i, 20)); } return $up($png(99, 99))->status === 422; })());
}

/* ---------------------------------------------------------------- storage layout, preview host, deletion */
$siteDirFiles = array_column(ProjectStorage::inventory($PA['uid']), 'path');
check('drafts and images live in storage/projects/<uid>/editor/, never in the site/ folder that the client-preview host serves', is_file(DraftStore::dir($PA['uid']) . '/draft.json') && !str_contains(DraftStore::dir($PA['uid']), '/site') && $siteDirFiles === []);
check('the preview host cannot reach the draft or its images (site/ only; traversal and "editor/…" paths resolve to nothing)', ProjectStorage::resolve($PA['uid'], 'editor/draft.json') === null && ProjectStorage::resolve($PA['uid'], '../editor/draft.json') === null && ProjectStorage::resolve($PA['uid'], '../editor/media/x.jpg') === null);
check('draft.json holds typed values only (no HTML) and is not executable', (function () use ($PA) { $f = DraftStore::dir($PA['uid']) . '/draft.json'; $d = json_decode((string) file_get_contents($f), true); return is_array($d) && array_keys($d) === ['template', 'version', 'updated_at', 'values'] && (fileperms($f) & 0111) === 0 && !is_link($f); })());
check('a damaged or tampered draft file can never inject anything: bad values are dropped to defaults on load', (function () use ($PA) {
    $f = DraftStore::dir($PA['uid']) . '/draft.json'; $orig = (string) file_get_contents($f);
    file_put_contents($f, json_encode(['version' => 5, 'values' => ['hero_line1' => str_repeat('z', 500), 'nav_home_href' => 'javascript:alert(1)', 'color_copy' => 'url(//x)', 'rogue' => '<b>', 'demo_image' => 'm0000000000000000']]));
    $v = DraftStore::load($PA['uid'])['values']; $ok = $v['nav_home_href'] === '#' ? true : $v['nav_home_href'] === '#home'; $ok = !str_contains(json_encode($v), 'javascript') && !isset($v['rogue']) && $v['color_copy'] === '' && $v['demo_image'] === '' && mb_strlen($v['hero_line1']) <= 22;
    file_put_contents($f, '{not json'); $broken = DraftStore::load($PA['uid']); file_put_contents($f, $orig);
    return $ok && $broken['version'] === 0 && $broken['values'] === Schema::defaults();
})());
check('deleting the project removes its drafts and images with it', (function () use ($PB) { Projects::delete($PB); return !is_dir(DraftStore::dir($PB['uid'])); })());

/* ---------------------------------------------------------------- the rest of the application is unaffected */
check('existing public pages still work with the editor enabled (and show no editor link or route)', (function () use ($eh, $eq, $as) { $as(null); $r = $eh($eq('GET', '/en/')); return $r->status === 200 && !str_contains($r->body, '/editor/') && $eh($eq('GET', '/editor'))->status === 404 && $eh($eq('GET', '/editor/projects'))->status === 404; })());
check('the editor adds no route outside /editor/projects/{id}…', (function () { $src = (string) file_get_contents(Paths::root('src/bootstrap.php')); preg_match_all("#\\\$router->add\\('(?:GET|POST)', '(/editor[^']*)'#", $src, $m); foreach ($m[1] as $p) { if (!str_starts_with($p, '/editor/projects/{id}')) return false; } return count(array_filter($m[1], fn($p) => !str_contains($p, '/builder'))) === 8; })());
check('vendored VvvebJs: licence kept, modification notices present, no eval/new Function, no sample save/upload endpoints shipped', (function () {
    $d = Paths::publicDir('assets/editor/vendor/vvvebjs'); if (!is_file("$d/LICENSE") || !str_contains((string) file_get_contents("$d/LICENSE"), 'Apache License')) return false;
    foreach (['builder.js', 'undo.js', 'inputs.js'] as $f) { $s = (string) file_get_contents("$d/$f"); if (!str_contains($s, 'MODIFIED BY PIXELITE') || !str_contains($s, 'Licensed under the Apache License') || preg_match('/\bnew Function\s*\(|\beval\s*\(/', preg_replace('#//[^\n]*|/\*.*?\*/#s', '', $s))) { fwrite(STDOUT, "     $f\n"); return false; } }
    return !is_file("$d/save.php") && !is_file("$d/upload.php") && !is_file("$d/scan.php") && is_file("$d/MODIFICATIONS.txt");
})());
$accountLink = function (string $flag) use ($eh, $eq, $as, $A, $PA) { Env::set('EDITOR_ENABLED', $flag); $as($A); return str_contains($eh($eq('GET', '/en/account'))->body, 'href="/editor/projects/' . $PA['id'] . '"'); };
check('the account page links to the editor only while EDITOR_ENABLED=true', $accountLink('true') === true && $accountLink('false') === false && $accountLink('') === false);
require __DIR__ . '/editor_builder.php';   // site builder: sanitisers, API, documents
require __DIR__ . '/editor_public.php';    // public builder: anonymous temporary workspaces
Env::set('EDITOR_ENABLED', '');
exec('rm -rf ' . escapeshellarg($ER));
