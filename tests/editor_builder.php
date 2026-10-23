<?php
declare(strict_types=1);

// Included by tests/editor.php (shares check(), $tmp, $eq, $eh, $as …). EXPERIMENTAL site builder: the HTML/CSS sanitisers (the security boundary now that
// customers author markup), the page store, the builder API (auth, tenancy, CSRF, versions, size limits, media list/delete) and the document headers.

use Pixelite\Editor\Builder\{BuilderController, CssSanitizer, HtmlSanitizer, PageRenderer, SiteStore};
use Pixelite\Editor\{DraftStore, Media};
use Pixelite\{Customers, Database, Env, Paths, ProjectStorage, Projects, Request};

Env::set('EDITOR_ENABLED', 'true');
echo "\nsite builder (experimental): sanitisers\n";
$san = new HtmlSanitizer(7);
$mid = 'm0123456789abcdef';
$hs = fn(string $h): string => $san->body($h)['html'] ?? '[REFUSED]';

// --- hostile HTML corpus: nothing executable may survive, whatever the trick
$evil = [
    'script tag' => '<script>alert(1)</script><p>hi</p>', 'script in svg' => '<svg><script>alert(1)</script></svg><p>x</p>', 'svg onload' => '<svg onload=alert(1)><circle/></svg>',
    'img onerror' => '<img src=x onerror=alert(1)>', 'img onerror uppercase' => '<IMG SRC=x ONERROR=alert(1)>', 'div onclick' => '<div onclick="alert(1)">x</div>', 'a onmouseover' => '<a href="#" onmouseover=alert(1)>x</a>',
    'js href' => '<a href="javascript:alert(1)">x</a>', 'js href entity' => '<a href="&#106;avascript:alert(1)">x</a>', 'js href hex entity' => '<a href="jav&#x61;script:alert(1)">x</a>', 'js href tab' => "<a href=\"java\tscript:alert(1)\">x</a>",
    'js href newline' => "<a href=\"java\nscript:alert(1)\">x</a>", 'js href space' => '<a href=" javascript:alert(1)">x</a>', 'js href upper' => '<a href="JaVaScRiPt:alert(1)">x</a>', 'data href' => '<a href="data:text/html,<script>alert(1)</script>">x</a>', 'vbscript' => '<a href="vbscript:x">x</a>',
    'math mglyph style' => '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>', 'noscript breakout' => '<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>', 'title breakout' => '<title><img src=x onerror=alert(1)></title>',
    'textarea breakout' => '<textarea><img src=x onerror=alert(1)></textarea>', 'xmp' => '<xmp><img src=x onerror=alert(1)></xmp>', 'plaintext' => '<plaintext><img src=x onerror=alert(1)>', 'iframe srcdoc' => '<iframe srcdoc="<script>alert(1)</script>"></iframe>',
    'iframe src' => '<iframe src="https://evil.example"></iframe>', 'object' => '<object data="x.swf"></object>', 'embed' => '<embed src="x.swf">', 'base' => '<base href="//evil.example/">', 'meta refresh' => '<meta http-equiv="refresh" content="0;url=javascript:alert(1)">',
    'link css' => '<link rel="stylesheet" href="//evil.example/x.css">', 'style tag' => '<style>@import "//evil.example/x.css"; body{background:url(//evil.example/x)}</style>', 'form action' => '<form action="//evil.example" method="post"><input formaction="//evil.example" type="submit"></form>',
    'button formaction' => '<button formaction="//evil.example">x</button>', 'inline css js url' => '<div style="background:url(javascript:alert(1))">x</div>', 'inline css expression' => '<div style="width:expression(alert(1))">x</div>',
    'inline css external url' => '<div style="background:url(//evil.example/x.png)">x</div>', 'inline css escape' => '<div style="color:\\72 ed;background:u\\72l(//evil.example)">x</div>', 'external img' => '<img src="https://evil.example/x.png">', 'protocol-relative img' => '<img src="//evil.example/x.png">',
    'svg data img' => '<img src="data:image/svg+xml,<svg onload=alert(1)>">', 'other project media' => '<img src="/editor/projects/999/media/' . 'm0123456789abcdef' . '">', 'attr breakout' => '<div title=\'"><script>alert(1)</script>\'>x</div>', 'attr breakout 2' => '<div title="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">x</div>',
    'comment' => '<!--[if IE]><script>alert(1)</script><![endif]--><p>x</p>', 'cdata' => '<![CDATA[<script>alert(1)</script>]]>', 'video' => '<video><source src=x onerror=alert(1)></video>', 'audio' => '<audio src=x onerror=alert(1)>', 'marquee' => '<marquee onstart=alert(1)>x</marquee>',
    'details ontoggle' => '<details open ontoggle=alert(1)>x</details>', 'input autofocus' => '<input autofocus onfocus=alert(1)>', 'srcset' => '<img srcset="x 1x" src="x">', 'id injection' => '<div id="x&quot; onclick=&quot;alert(1)">x</div>',
    'class injection' => '<div class="a&quot; onclick=&quot;alert(1)">x</div>', 'data attr' => '<div data-x="&quot;><img src=x onerror=alert(1)>">x</div>', 'namespaced attr' => '<a xlink:href="javascript:alert(1)">x</a>', 'xml pi' => '<?xml version="1.0"?><p>x</p>',
    'template' => '<template><script>alert(1)</script></template>', 'canvas+map' => '<map name=m><area href="javascript:alert(1)"></map>', 'nested mix' => '<p><b><svg><desc><![CDATA[</desc><script>alert(1)</script>]]></svg></b></p>',
    'null bytes' => "<scr\0ipt>alert(1)</scr\0ipt><img src=x on\0error=alert(1)>", 'unclosed' => '<div><p><a href="https://example.com/"><b>x', 'dialog' => '<dialog open onclose=alert(1)>x</dialog>',
];
$banned = ['<script', 'onerror', 'onload', 'onclick', 'onmouseover', 'onfocus', 'ontoggle', 'onstart', 'onclose', 'javascript:', 'vbscript:', '<svg', '<math', '<iframe', 'srcdoc', 'formaction', '<style', '<meta', '<base', '<link', '<object', '<embed', '<noscript', '<template', '<title', '<xmp', '<plaintext', 'evil.example', 'expression(', 'xlink', 'srcset', '<!--', '<![cdata', '<?xml'];
$leaks = [];
$notFix = [];
foreach ($evil as $name => $in) {
    $out = $hs($in);
    if ($out !== '[REFUSED]' && preg_match('/<[a-z][^>]*\b(?:on[a-z]+|srcdoc|formaction)=/i', $out)) { $leaks[] = "$name → live handler attribute in " . substr($out, 0, 120); }
    if ($out === '[REFUSED]') { continue; }   // refusing is always acceptable
    $visible = preg_replace('/(\\s[a-z-]+)="[^"]*"/', '$1', $out) ?? $out;   // attribute VALUES are escaped text; markup outside them is what must be clean
    foreach ($banned as $b) { if (stripos($visible, $b) !== false) { $leaks[] = "$name → «{$b}» in " . substr($out, 0, 120); } }
    if ($hs($out) !== $out) { $notFix[] = $name; }
}
check('sanitiser: ' . count($evil) . ' hostile inputs (XSS, mXSS, svg/math/noscript/title/textarea breakouts, entity/tab/newline-split javascript:, handlers, srcdoc, formaction, @import, escapes, external/other-project images …) leave nothing executable', $leaks === [], implode(' | ', array_slice($leaks, 0, 4)));
check('sanitiser: the output is a fixpoint (sanitising it again changes nothing) for the whole corpus', $notFix === [], implode(', ', $notFix));
check('sanitiser: structural check – re-parsing every output finds only allowlisted tags, no on* attributes, no style/script elements', (function () use ($evil, $hs) {
    $allowed = (new ReflectionClass(HtmlSanitizer::class))->getReflectionConstant('ALLOWED')->getValue();
    foreach ($evil as $in) {
        $out = $hs($in); if ($out === '[REFUSED]') { continue; }
        libxml_use_internal_errors(true); $d = new DOMDocument(); $d->loadHTML('<body>' . $out . '</body>');
        foreach ($d->getElementsByTagName('*') as $el) {
            if (!in_array($el->nodeName, array_merge($allowed, ['html', 'body']), true)) { return false; }
            foreach ($el->attributes as $a) { if (str_starts_with($a->name, 'on') || in_array($a->name, ['srcdoc', 'formaction', 'action', 'srcset'], true)) { return false; } }
        }
    }
    return true;
})());
check('sanitiser keeps ordinary content: headings, text, lists, tables, buttons, forms, classes, safe links (rel added for _blank), own images', (function () use ($hs, $mid) {
    $in = '<section class="py-5 text-center" id="hero"><div class="container"><h1 class="display-4">Hello &amp; welcome</h1><p class="lead">Text <strong>bold</strong> <em>it</em></p><a class="btn btn-primary" href="https://example.com/x?y=1" target="_blank">Go</a> <a href="#about">Anchor</a> <a href="mailto:hi@example.com">Mail</a>'
        . '<ul><li>One</li><li>Two</li></ul><table class="table"><tr><td colspan="2">c</td></tr></table><form><label for="n">Name</label><input id="n" type="text" name="n" placeholder="Your name" required><textarea name="m" rows="3">a&lt;b</textarea><button type="submit">Send</button></form>'
        . '<img src="/editor/projects/7/media/' . $mid . '" alt="Logo" width="120" class="img-fluid"></div></section>';
    $o = $hs($in);
    foreach (['<section class="py-5 text-center" id="hero">', '<h1 class="display-4">Hello &amp; welcome</h1>', '<strong>bold</strong>', 'href="https://example.com/x?y=1" target="_blank" rel="noopener noreferrer"', 'href="#about"', 'href="mailto:hi@example.com"', '<li>Two</li>', 'colspan="2"', '<input id="n" type="text" name="n" placeholder="Your name" required>', '<textarea name="m" rows="3">a&lt;b</textarea>', '<img src="/editor/projects/7/media/' . $mid . '" alt="Logo" width="120" class="img-fluid">'] as $needle) {
        if (!str_contains($o, $needle)) { fwrite(STDOUT, "     missing: $needle\n     got: " . substr($o, 0, 300) . "\n"); return false; }
    }
    return true;
})());
check('sanitiser: size and depth limits refuse instead of storing (300 KB, 6000 nodes, depth 40)', $san->body(str_repeat('<p>x</p>', 60000)) === null && $san->body(str_repeat('<div>', 60) . 'x' . str_repeat('</div>', 60)) === null && $san->body(str_repeat('<i></i>', 7000)) === null && $san->body("\xff\xfe") === null);
check('sanitiser: links – https/http/mailto/tel/#anchor only', $san->linkOk('https://example.com') && $san->linkOk('#top') && $san->linkOk('tel:+420123456789') && !$san->linkOk('javascript:alert(1)') && !$san->linkOk('//evil.example') && !$san->linkOk('/relative') && !$san->linkOk('data:text/html,x') && !$san->linkOk('https://u:p@example.com'));
check('sanitiser: image URLs – only this project\'s uploads and the bundled placeholders', $san->imageUrlOk('/editor/projects/7/media/' . $mid) && !$san->imageUrlOk('/editor/projects/8/media/' . $mid) && !$san->imageUrlOk('https://example.com/a.png') && !$san->imageUrlOk('/editor/projects/7/media/../../x') && $san->imageUrlOk('/assets/editor/placeholders/image.svg') && !$san->imageUrlOk('/assets/editor/placeholders/../../site.css'));

// --- CSS
$cssOk = fn(string $u): bool => $san->imageUrlOk($u);
$cs = fn(string $c): string => CssSanitizer::stylesheet($c, $cssOk);
$cssEvil = [
    '@import url(//evil.example/x.css); body{color:red}', '@import "x";', 'body{background:url(javascript:alert(1))}', 'a{width:expression(alert(1))}', '</style><script>alert(1)</script>', 'b\\6fdy{color:red}', 'p{color:\\72 ed}', '@font-face{font-family:x;src:url(//evil.example/f.woff)}',
    'div{-moz-binding:url(//evil.example/x.xml#b)}', 'div{behavior:url(x.htc)}', 'div{background:url(//evil.example/x)}', 'div{background-image:url(https://evil.example/x.png)}', 'div{content:"</style><script>1</script>"}', 'a{color:red;;}}}{{', '@charset "x"; p{color:red}',
    'div{background:image-set("//evil.example/x" 1x)}', 'div{background:url(data:image/svg+xml,%3Csvg onload=alert(1)%3E)}', 'div{background:url(data:text/html;base64,PHNjcmlwdD4=)}', '@namespace url(http://x); p{color:red}', 'div{color:red!important;position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999}',
    '@media screen{@import "x";}', '@supports (display:grid){p{color:red}}', 'p{color:red} </STYLE', 'p{width:calc(1px + url(x))}', 'x{--a:url(//evil.example/x)}', 'div{background:URL ( //evil.example/x )}', 'a{background:u/**/rl(//evil.example/x)}', 'p::after{content:"\\3c script\\3e"}',
];
$cssBanned = ['@import', '@charset', '@namespace', '@font-face', '@supports', 'javascript:', 'expression', 'binding', 'behavior', 'evil.example', '</', '<', '\\', 'image-set', 'data:text', 'svg+xml', 'url(//'];
$cl = [];
foreach ($cssEvil as $c) { $o = $cs($c); foreach ($cssBanned as $b) { if (stripos($o, $b) !== false) { $cl[] = substr($c, 0, 40) . " → «{$b}»: $o"; } } if ($cs($o) !== $o) { $cl[] = 'not fixpoint: ' . substr($c, 0, 40); } }
check('css: ' . count($cssEvil) . ' hostile stylesheets (@import, js/external urls, escapes, </style>, expression, binding, font-face, image-set, data:html/svg …) leave nothing dangerous and are fixpoints', $cl === [], implode(' | ', array_slice($cl, 0, 3)));
check('css: ordinary styling survives (selectors, media queries, keyframes, custom properties, !important, own-image urls, gradients, grid/flex) as canonical text', (function () use ($cs, $mid) {
    $o = $cs("/* c */ body { font-family: 'Onest', sans-serif; margin:0 }\n.hero > h1:hover, #a .b[data-x=\"1\"] { color: #1b45ff !important; background: linear-gradient(135deg, #fff, rgba(0,0,0,.5)) }\n@media (max-width: 600px) { .grid { display:grid; grid-template-columns: repeat(2, 1fr); gap: 8px } }\n@keyframes spin { from { transform: rotate(0deg) } to { transform: rotate(360deg) } }\n:root { --brand: #0af }\n.x { background: url(/editor/projects/7/media/$mid) center/cover; color: var(--brand) }");
    foreach (["body{font-family:'Onest', sans-serif;margin:0}", '.hero > h1:hover, #a .b[data-x="1"]{color:#1b45ff!important;background:linear-gradient(135deg, #fff, rgba(0,0,0,.5))}', '@media (max-width: 600px){.grid{display:grid;grid-template-columns:repeat(2, 1fr);gap:8px}}', '@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}', ':root{--brand:#0af}', "url(/editor/projects/7/media/$mid) center/cover"] as $needle) {
        if (!str_contains($o, $needle)) { fwrite(STDOUT, "     missing: $needle\n     got: $o\n"); return false; }
    }
    return true;
})());
check('css: unknown properties and non-media at-rules are dropped, size limit refuses', $cs('div{evil-prop:1;color:red}') === 'div{color:red}' && $cs(str_repeat('a{color:red}', 12000)) === '' && $cs('@page{margin:0}') === '');
CssSanitizer::$notes = []; $cs('@import "x"; p{color:red}');
check('css/html: the customer is told what was removed (notes)', CssSanitizer::$notes !== [] && count($san->body('<script>1</script><div onclick="x">a</div>')['notes']) >= 2);
check('every section and block the builder offers survives the sanitiser unchanged (nothing in the library is silently stripped)', (function () use ($san) {
    $js = (string) file_get_contents(Paths::root('public/assets/editor/px-blocks.js'));
    preg_match_all('/[SB]\("(pixelite\/[a-z0-9-]+)", "[^"]+", `(.*?)`\);/s', $js, $m, PREG_SET_ORDER);
    if (count($m) < 25) { fwrite(STDOUT, "     found only " . count($m) . " items\n"); return false; }
    $norm = static fn(string $h): string => preg_replace('/\s+/', ' ', preg_replace('/ ?\/?>/', '>', str_replace('\$' . '{IMG}', '/assets/editor/placeholders/image.svg', str_replace('\$' . '{AV}', '/assets/editor/placeholders/avatar.svg', $h)))) ?? $h;
    foreach ($m as [, $id, $html]) {
        $html = str_replace(['${IMG}', '${AV}'], ['/assets/editor/placeholders/image.svg', '/assets/editor/placeholders/avatar.svg'], $html);
        $r = $san->body($html);
        if ($r === null || $r['notes'] !== []) { fwrite(STDOUT, "     $id: " . json_encode($r['notes'] ?? 'refused') . "\n"); return false; }
        $strip = static fn(string $h): string => preg_replace('/\s+/', '', str_replace(['style="max-width: 640px"', 'style="max-width: 760px"', 'style="max-width: 360px"'], ['style="max-width:640px"', 'style="max-width:760px"', 'style="max-width:360px"'], $h)) ?? $h;
        if ($strip($r['html']) !== $strip($html)) { fwrite(STDOUT, "     $id changed:\n       in : " . substr($strip($html), 0, 200) . "\n       out: " . substr($strip($r['html']), 0, 200) . "\n"); return false; }
    }
    return true;
})());
check('the placeholder pictures exist locally', is_file(Paths::publicDir('assets/editor/placeholders/image.svg')) && is_file(Paths::publicDir('assets/editor/placeholders/avatar.svg')));
check('page renderer: server-owned head (Bootstrap + base css, sanitised style), tampered stored data cannot inject, edit helper only in the canvas', (function () {
    $page = ['title' => 'T <b>x</b>', 'html' => '<p>ok</p><script>alert(1)</script><img src=x onerror=1>', 'css' => 'p{color:red}</style><script>alert(1)</script>'];
    $d = PageRenderer::document($page, 7, true); $v = PageRenderer::document($page, 7, false);
    return !str_contains($d, '<script') && !str_contains($d, 'onerror') && str_contains($d, '<title>T &lt;b&gt;x&lt;/b&gt;</title>') && str_contains($d, 'bootstrap.min.css') && str_contains($d, '<style id="vvvebjs-styles">p{color:red}</style>') && str_contains($d, 'builder-canvas.css') && !str_contains($v, 'builder-canvas.css') && str_contains($v, '<p>ok</p>');
})());

echo "site builder (experimental): API\n";
$BA = $mkProject($A, 'Builder A'); $BB = $mkProject($B, 'Builder B');
$bb = fn(array $p) => '/editor/projects/' . $p['id'] . '/builder';
$bpost = function (string $path, array $post, ?array $hdr = null, ?array $project = null) use ($eh, $eq, $BA, $bb, $XHR) { return $eh($eq('POST', $bb($project ?? $BA) . $path, $post + ['_csrf' => 'ed-token'], $hdr ?? $XHR)); };
$ver = fn(array $p = null) => SiteStore::load(($p ?? $BA)['uid'])['version'];
$as($A);
check('builder editor page: 200, same strict page CSP as the Vantage editor (script-src self, no eval/inline), not frameable', (function () use ($eh, $eq, $bb, $BA) { $r = $eh($eq('GET', $bb($BA))); $c = (string) ($r->headers['Content-Security-Policy'] ?? ''); return $r->status === 200 && str_contains($c, "script-src 'self';") && !str_contains($c, 'unsafe-eval') && !preg_match("/script-src[^;]*unsafe-inline/", $c) && str_contains($c, "frame-ancestors 'none'") && ($r->headers['X-Frame-Options'] ?? '') === 'DENY'; })());
$bRoutes = fn(array $p) => [['GET', ''], ['GET', '/site'], ['POST', '/pages'], ['GET', '/pages/index/canvas'], ['GET', '/pages/index/preview'], ['POST', '/pages/index'], ['POST', '/pages/index/delete'], ['GET', '/media'], ['POST', '/media/m0123456789abcdef/delete']];
check('builder: guests get a redirect (page) or 401 (API); customer B gets the identical 404 for A\'s project on every route; flag off = 404', (function () use ($eh, $eq, $as, $bRoutes, $bb, $BA, $A, $B, $XHR) {
    $as(null); foreach ($bRoutes($BA) as [$m, $s]) { $r = $eh($eq($m, $bb($BA) . $s, ['_csrf' => 'ed-token'], $XHR)); if ($s === '' ? $r->status !== 302 : $r->status !== 401) return false; }
    $as($B); foreach ($bRoutes($BA) as [$m, $s]) { $r = $eh($eq($m, $bb($BA) . $s, ['_csrf' => 'ed-token', 'base_version' => '0', 'html' => '<p>x</p>', 'css' => '', 'title' => 'x', 'slug' => 'zz'], $XHR)); if ($r->status !== 404 || str_contains($r->body, 'Builder A')) return false; }
    Env::set('EDITOR_ENABLED', 'false'); $as($A); foreach ($bRoutes($BA) as [$m, $s]) { if ($eh($eq($m, $bb($BA) . $s, ['_csrf' => 'ed-token'], $XHR))->status !== 404) { Env::set('EDITOR_ENABLED', 'true'); return false; } }
    Env::set('EDITOR_ENABLED', 'true'); $as($A); return true;
})());
check('builder save: CSRF token, X-Requested-With header, numeric version are all required', $bpost('/pages/index', ['_csrf' => '', 'base_version' => '0', 'html' => '<p>x</p>', 'css' => '', 'title' => 'x'])->status === 403 && $bpost('/pages/index', ['base_version' => '0', 'html' => '<p>x</p>', 'css' => '', 'title' => 'x'], [])->status === 403 && $bpost('/pages/index', ['base_version' => 'x', 'html' => '<p>x</p>', 'css' => '', 'title' => 'x'])->status === 400 && $ver() === 0);
$r = $bpost('/pages/index', ['base_version' => '0', 'title' => 'Home', 'html' => '<section class="py-5"><h1>Hi</h1><img src=x onerror=alert(1)><script>alert(1)</script></section>', 'css' => 'h1{color:red}@import "x";']);
$j = json_decode($r->body, true);
check('builder save: the reply carries the SANITISED page (not what was sent), the version moves on and the customer is told what was removed', $r->status === 200 && $j['ok'] === true && $j['version'] === 1 && !str_contains($j['html'], 'script') && !str_contains($j['html'], 'onerror') && str_contains($j['html'], '<h1>Hi</h1>') && $j['css'] === 'h1{color:red}' && count($j['notes']) >= 2);
$stored = SiteStore::load($BA['uid']);
check('builder save: only sanitised content is on disk, in editor/builder/site.json (not in site/), not executable', !str_contains(json_encode($stored), 'onerror') && is_file(SiteStore::dir($BA['uid']) . '/site.json') && (fileperms(SiteStore::dir($BA['uid']) . '/site.json') & 0111) === 0 && ProjectStorage::inventory($BA['uid']) === [] && $stored['pages']['index']['css'] === 'h1{color:red}');
check('builder save: stale version → 409, unknown page → 422, bad title/size → 400/413, nothing changes', $bpost('/pages/index', ['base_version' => '0', 'html' => '<p>stale</p>', 'css' => '', 'title' => 'x'])->status === 409 && $bpost('/pages/nope', ['base_version' => (string) $ver(), 'html' => '<p>x</p>', 'css' => '', 'title' => 'x'])->status === 422
    && $bpost('/pages/index', ['base_version' => (string) $ver(), 'html' => '<p>x</p>', 'css' => '', 'title' => str_repeat('t', 81)])->status === 400 && $bpost('/pages/index', ['base_version' => (string) $ver(), 'html' => str_repeat('a', 300001), 'css' => '', 'title' => 'x'])->status === 413
    && $bpost('/pages/index', ['base_version' => (string) $ver(), 'html' => '<p>x</p>', 'css' => str_repeat('a', 100001), 'title' => 'x'])->status === 413 && $ver() === 1 && !str_contains(SiteStore::load($BA['uid'])['pages']['index']['html'], 'stale'));
check('builder save: an HTML bomb (too many nodes / too deep) is refused (422), nothing stored', $bpost('/pages/index', ['base_version' => (string) $ver(), 'html' => str_repeat('<i></i>', 7000), 'css' => '', 'title' => 'x'])->status === 422 && $ver() === 1);
$c = $eh($eq('GET', $bb($BA) . '/pages/index/canvas')); $pv = $eh($eq('GET', $bb($BA) . '/pages/index/preview'));
$cc = (string) $c->headers['Content-Security-Policy'];
check('canvas/preview: sandboxed (allow-same-origin only), script-src none, default-src none, no forms, framable only by the same origin, never cached', $c->status === 200 && $pv->status === 200 && str_starts_with($cc, 'sandbox allow-same-origin;') && str_contains($cc, "script-src 'none'") && str_contains($cc, "default-src 'none'") && str_contains($cc, "form-action 'none'") && str_contains($cc, "frame-ancestors 'self'") && !str_contains($cc, 'unsafe-eval') && !str_contains($cc, 'allow-scripts') && ($c->headers['X-Frame-Options'] ?? '') === 'SAMEORIGIN' && ($c->headers['Cache-Control'] ?? '') === 'no-store' && $pv->headers['Content-Security-Policy'] === $cc);
check('canvas: renders the stored page with the helper css; preview without; neither contains script', str_contains($c->body, '<h1>Hi</h1>') && str_contains($c->body, 'builder-canvas.css') && !str_contains($pv->body, 'builder-canvas.css') && !preg_match('/<script/i', $c->body . $pv->body) && $eh($eq('GET', $bb($BA) . '/pages/nope/canvas'))->status === 404);
check('a page that was tampered with on disk is re-sanitised when rendered', (function () use ($BA, $eh, $eq, $bb) {
    $f = SiteStore::dir($BA['uid']) . '/site.json'; $orig = (string) file_get_contents($f); $d = json_decode($orig, true);
    $d['pages']['index']['html'] = '<p>t</p><script>alert(1)</script><img src="//evil.example/x" onerror=1>'; $d['pages']['index']['css'] = 'p{background:url(//evil.example/x)}</style><script>1</script>';
    file_put_contents($f, json_encode($d)); $b = $eh($eq('GET', $bb($BA) . '/pages/index/canvas'))->body; file_put_contents($f, $orig);
    return !str_contains($b, '<script') && !str_contains($b, 'evil.example') && !str_contains($b, 'onerror') && str_contains($b, '<p>t</p>');
})());
check('pages: create (validated slug/title, limit 10), duplicate refused, delete (not the home page), versions advance', (function () use ($bpost, $ver, $BA) {
    $a = $bpost('/pages', ['slug' => 'about', 'title' => 'About us', 'base_version' => (string) $ver()]); if ($a->status !== 200) return false;
    foreach (['../x', 'About', 'a b', '', str_repeat('a', 41), 'a/b'] as $bad) { if ($bpost('/pages', ['slug' => $bad, 'title' => 'x', 'base_version' => (string) $ver()])->status !== 422) return false; }
    if ($bpost('/pages', ['slug' => 'about', 'title' => 'x', 'base_version' => (string) $ver()])->status !== 422) return false;
    for ($i = 1; $i <= 12; $i++) { $bpost('/pages', ['slug' => "p$i", 'title' => "P$i", 'base_version' => (string) $ver()]); }
    if (count(SiteStore::load($BA['uid'])['pages']) !== SiteStore::MAX_PAGES) return false;
    if ($bpost('/pages/index/delete', ['base_version' => (string) $ver()])->status !== 422) return false;
    $d = $bpost('/pages/about/delete', ['base_version' => (string) $ver()]);
    return $d->status === 200 && !isset(SiteStore::load($BA['uid'])['pages']['about']);
})());
check('site listing returns slugs and titles (JSON), in order', (function () use ($eh, $eq, $bb, $BA) { $j = json_decode($eh($eq('GET', $bb($BA) . '/site'))->body, true); return $j['ok'] && $j['pages'][0]['slug'] === 'index' && isset($j['version']); })());

echo "site builder (experimental): media list / delete\n";
if (Media::available()) {
    $up2 = function (array $project) use ($tmp, $eq, $as, $A, $XHR, $base) { $im = imagecreatetruecolor(30, 20); ob_start(); imagepng($im); $png = (string) ob_get_clean(); $f = "$tmp/bm-" . bin2hex(random_bytes(3)); file_put_contents($f, $png); Database::pdo()->exec('DELETE FROM rate_limits'); $as($A);
        return json_decode((new Pixelite\Editor\EditorController(false))->uploadMedia($eq('POST', $base($project) . '/media', ['_csrf' => 'ed-token'], $XHR, ['image' => ['name' => 'a.png', 'tmp_name' => $f, 'size' => strlen($png), 'error' => 0]]), ['id' => (string) $project['id']])->body, true)['id'] ?? ''; };
    $m1 = $up2($BA); $m2 = $up2($BA); $as($A);
    $lst = json_decode($eh($eq('GET', $bb($BA) . '/media'))->body, true);
    check('media list: the owner sees own images with URLs and an "in use" flag; nothing of other projects', $lst['ok'] && count($lst['images']) === 2 && $lst['images'][0]['url'] === '/editor/projects/' . $BA['id'] . '/media/' . $lst['images'][0]['id'] && $lst['images'][0]['used'] === false);
    $page = '<img src="/editor/projects/' . $BA['id'] . '/media/' . $m1 . '" alt="x">';
    $bpost('/pages/index', ['base_version' => (string) $ver(), 'html' => $page, 'css' => '', 'title' => 'Home']);
    check('the sanitiser keeps this project\'s own uploaded image on a page', str_contains(SiteStore::load($BA['uid'])['pages']['index']['html'], $m1));
    check('media delete: refused (409) while a page uses the image, allowed when unused, 404 for unknown ids; B cannot delete A\'s image', $bpost('/media/' . $m1 . '/delete', [])->status === 409 && $bpost('/media/' . $m2 . '/delete', [])->status === 200 && !Media::exists($BA['uid'], $m2) && $bpost('/media/' . $m2 . '/delete', [])->status === 404
        && (function () use ($as, $B, $m1, $bpost, $BA) { $as($B); $s = $bpost('/media/' . $m1 . '/delete', [], null, $BA)->status; return $s === 404; })());
    $as($A);
    check('another project\'s image id cannot be placed on this project\'s page (the sanitiser drops it)', (function () use ($bpost, $ver, $BA, $m1, $BB) { $bpost('/pages/index', ['base_version' => (string) $ver(), 'html' => '<img src="/editor/projects/' . $BB['id'] . '/media/' . $m1 . '">', 'css' => '', 'title' => 'Home']); return !str_contains(SiteStore::load($BA['uid'])['pages']['index']['html'], '/media/'); })());
}
check('builder rate limit: saves are limited per customer (429 with Retry-After)', (function () use ($bpost, $ver) { Database::pdo()->exec('DELETE FROM rate_limits'); $last = 0; for ($i = 0; $i < 245; $i++) { $r = $bpost('/pages/index', ['base_version' => 'x', 'html' => '', 'css' => '', 'title' => 'x']); $last = $r->status; if ($last === 429) { return isset($r->headers['Retry-After']); } } return false; })());
Database::pdo()->exec('DELETE FROM rate_limits');
check('builder routes exist only under /editor/projects/{id}/builder… and the documents ship with the fixed CSP constant', (function () { $s = (string) file_get_contents(Paths::root('src/bootstrap.php')); preg_match_all("#\\\$router->add\\('(?:GET|POST)', '(/editor[^']*)'#", $s, $m); foreach ($m[1] as $p) { if (!str_starts_with($p, '/editor/projects/{id}')) return false; } return count(array_filter($m[1], fn($p) => str_contains($p, '/builder'))) === 9; })());
