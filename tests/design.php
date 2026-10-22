<?php
declare(strict_types=1);

// Included at the end of tests/run.php. The NEW design is the production default; tests/run.php above exercises the LEGACY presentation (markup assertions)
// and this file proves the new default, the /legacy switch, canonical/noindex handling, the admin screens and re-runs tests/features.php on the new templates.

use Pixelite\{Csrf, Customers, Database, Design, Env, I18n, Request};

echo "\nnew design as the default\n";
Env::set('LEADS_DATABASE_PATH', "$tmp/new.sqlite");
Env::set('PROJECTS_DIR', "$tmp/projects-new");
Database::reset();
Env::set('DESIGN_DEFAULT', 'new');
Env::set('APP_URL', 'https://pixelite.cz');
$dq = fn(string $m, string $p, array $post = [], array $q = [], string $ip = '203.0.113.30', array $extra = []) => new Request($m, $p, $post, $q, ['REMOTE_ADDR' => $ip] + $extra);
$dg = fn(string $p, array $q = [], string $ip = '203.0.113.30') => Pixelite\handle($dq('GET', $p, [], $q, $ip, ['REQUEST_URI' => $p . ($q ? '?' . http_build_query($q) : '')]));
$_SESSION = [];

$routes = ['', 'pricing', 'process', 'contact', 'team', 'careers', 'privacy', 'cookies', 'terms', 'order', 'templates', 'templates/lumora', 'templates/asterform', 'login', 'register', 'portfolio'];
$noindex = ['login', 'register', 'portfolio'];
Design::set('new');
check('Design: new is the default, legacy the alternate', Design::default() === 'new' && Design::alternate() === 'legacy' && Design::prefix() === '' && Design::prefix('legacy') === '/legacy');
foreach (I18n::LOCALES as $lg) {
    $bad = [];
    foreach ($routes as $pg) {
        $_SESSION = [];
        $r = $dg("/$lg/$pg");
        $b = $r->body;
        $path = $pg;
        $idx = !in_array($pg, $noindex, true);
        $ok = $r->status === 200 && str_contains($b, '/assets/site.css') && !str_contains($b, '/assets/style.css') && !str_contains($b, '/assets/script.js')
            && str_contains($b, "<html lang=\"$lg\">") && substr_count($b, '<h1') === 1
            && str_contains($b, 'rel="canonical" href="https://pixelite.cz/' . $lg . '/' . $path . '"') | ($pg === '' && str_contains($b, 'rel="canonical" href="https://pixelite.cz/' . $lg . '/"'))
            && str_contains($b, $idx ? 'content="index,follow"' : 'content="noindex,nofollow"')
            && substr_count($b, 'data-design-switch') === 1 && str_contains($b, 'href="/legacy/' . $lg . '/' . $path . '" rel="nofollow" data-design-switch');
        if (!$ok) { $bad[] = $pg . ':' . $r->status; }
    }
    check("new design ($lg): every public page renders the new design with the correct canonical/robots and a Legacy link in the footer (not in the navigation) pointing at /legacy/$lg/…", $bad === [], implode(' ', $bad));
}
foreach (['en' => 'Legacy website', 'cs' => 'Původní web', 'ru' => 'Прежняя версия сайта', 'uk' => 'Попередня версія сайту'] as $lg => $label) {
    $b = $dg("/$lg/pricing")->body;
    check("legacy link ($lg) is localized, discreet and in the footer (not in the navigation, not a banner)", str_contains($b, '>' . $label . '</a>') && !preg_match('#<(?:div|aside|section)[^>]*(?:legacy|design-banner)#i', $b) && substr_count($b, 'class="footer__link footer__link--version"') === 1 && !str_contains($b, 'pill__link--version'));
}
check('new design pages are CSP-clean: no inline <script>, <style> or style="" attributes, no third-party hosts', (function () use ($dg, $routes) {
    foreach (I18n::LOCALES as $lg) { foreach ($routes as $pg) {
        $_SESSION = []; $b = $dg("/$lg/$pg")->body;
        if (preg_match('/<script(?![^>]*\bsrc=)(?![^>]*ld\+json)[^>]*>|<style\b|\sstyle="|\son[a-z]+="/', $b) || preg_match('#(src|href)="https?://(?!pixelite\.cz|coi\.gov\.cz)#', $b)) { return false; }
    } }
    return true;
})());
$_SESSION = [];
$home = $dg('/en/')->body;
check('landing page: one notebook (hero-laptop) with the opening animation markup, the shader canvas, the new scripts only (no legacy script.js/style.css)', substr_count($home, 'class="hero-laptop"') === 1 && str_contains($home, 'class="lp-lid"') && str_contains($home, 'id="hero-canvas"') && str_contains($home, '/assets/site.js') && !str_contains($home, '/assets/script.js') && substr_count($home, 'class="laptop"') === 1);
check('landing page keeps its calls to action (start a project, services anchor) and all package cards with their order links', str_contains($home, 'href="/en/order"') && str_contains($home, 'href="#services"') && substr_count($home, 'data-package=') === 3 && str_contains($home, 'href="/en/order?package=template"') && str_contains($home, '2,999'));
check('new design assets exist (css, scripts, theme)', array_reduce(['site.css', 'site.js', 'site-theme.js', 'team.js', 'theme-init.js', 'consent.js', 'templates.js', 'admin.js'], fn($c, $f) => $c && is_file(Pixelite\Paths::publicDir('assets/' . $f)), true) && is_file(Pixelite\Paths::publicDir('assets/style.css')));
check('template folders: every legacy page/error/admin template has a new-design counterpart (same view contract)', (function () {
    foreach (['pages', 'errors', 'admin'] as $d) { foreach (glob(Pixelite\Paths::root("templates/legacy/$d/*.php")) as $f) { if (!is_file(Pixelite\Paths::root("templates/$d/" . basename($f)))) { fwrite(STDOUT, "     missing $d/" . basename($f) . "\n"); return false; } } }
    return true;
})());

echo "legacy design under /legacy\n";
foreach (I18n::LOCALES as $lg) {
    $bad = [];
    foreach ($routes as $pg) {
        $_SESSION = [];
        $r = $dg("/legacy/$lg/$pg");
        $b = $r->body;
        $links = preg_match_all('#href="/(?:en|cs|ru|uk)/[^"]*"#', $b, $m);
        $ok = $r->status === 200 && str_contains($b, '/assets/style.css') && !str_contains($b, '/assets/site.css') && str_contains($b, 'content="noindex,follow"')
            && (str_contains($b, 'rel="canonical" href="https://pixelite.cz/' . $lg . '/' . $pg . '"') || ($pg === '' && str_contains($b, 'rel="canonical" href="https://pixelite.cz/' . $lg . '/"')))
            && !str_contains($b, 'hreflang="' . $lg . '" href="https://pixelite.cz/legacy') && !str_contains($b, '/legacy/') === false
            && substr_count($b, 'data-design-switch') === 1 && str_contains($b, 'href="/' . $lg . '/' . $pg . '" rel="nofollow"') && $links === 1;
        if (!$ok) { $bad[] = "$pg:{$r->status}:links=$links"; }
    }
    check("legacy ($lg): every page renders the legacy design under /legacy, noindex,follow, canonical = the new-design URL, ONE way back to the new design, all other links stay inside /legacy", $bad === [], implode(' ', $bad));
}
check('legacy: the way back is localized and discreet (menu item)', str_contains($dg('/legacy/ru/pricing')->body, 'data-design-switch>Новый дизайн</a>') && str_contains($dg('/legacy/uk/pricing')->body, 'data-design-switch>Новий дизайн</a>') && str_contains($dg('/legacy/cs/pricing')->body, 'data-design-switch>Nový design</a>') && str_contains($dg('/legacy/en/pricing')->body, 'data-design-switch>New design</a>'));
check('legacy: language switch keeps the legacy design and the page', (function () use ($dg) { $b = $dg('/legacy/en/pricing')->body; foreach (I18n::LOCALES as $l) { if (!str_contains($b, 'href="/legacy/' . $l . '/pricing" hreflang="' . $l . '"')) return false; } return true; })());
check('legacy: hreflang/x-default alternates point at the DEFAULT design URLs (no duplicate indexing)', (function () use ($dg) { $b = $dg('/legacy/cs/process')->body; foreach (I18n::LOCALES as $l) { if (!str_contains($b, '<link rel="alternate" hreflang="' . $l . '" href="https://pixelite.cz/' . $l . '/process">')) return false; } return !str_contains($b, 'pixelite.cz/legacy'); })());
check('sitemap lists only default-design URLs', !str_contains($dg('/sitemap.xml')->body, '/legacy'));
$r = $dg('/legacy');
check('/legacy redirects to /legacy/<best language>/ (no loop, stays legacy)', $r->status === 302 && ($r->headers['Location'] ?? '') === '/legacy/en/');
$r = Pixelite\handle($dq('GET', '/legacy', [], [], '203.0.113.31', ['REQUEST_URI' => '/legacy', 'HTTP_ACCEPT_LANGUAGE' => 'uk-UA,uk;q=0.9']));
check('/legacy honours the browser language', ($r->headers['Location'] ?? '') === '/legacy/uk/');
$r = Pixelite\handle($dq('GET', '/legacy/en', [], [], '203.0.113.31', ['REQUEST_URI' => '/legacy/en']));
check('/legacy/en is canonicalised to /legacy/en/ (301, prefix kept)', $r->status === 301 && ($r->headers['Location'] ?? '') === '/legacy/en/');
foreach (['/legacy/admin', '/legacy/admin/login', '/legacy/sitemap.xml', '/legacy/robots.txt', '/legacy/xx/', '/legacy/p/' . str_repeat('A', 43) . '/', '/legacy/assets/style.css', '/new/en/', '/new'] as $u) {
    check("not routed: $u (staff, utility and unknown paths exist once, in the default design)", $dg($u)->status === 404 || ($u === '/new' && $dg($u)->status === 404));
}
check('the staff area is not available in the legacy design (no second admin on a prefix)', $dg('/legacy/admin')->status === 404 && $dg('/admin/login')->status === 200);

echo "forms and journeys in both designs\n";
foreach (['' => '/en/order', '/legacy' => '/legacy/en/order'] as $pre => $path) {
    $_SESSION = [];
    $f = $dg($path)->body;
    check("order form ($path): posts to itself inside the same design", str_contains($f, 'action="' . $path . '"') && str_contains($f, 'name="_csrf"'));
    $_SESSION['form_ts'] = time() - 30; $tok = $_SESSION['_csrf'] ?? Csrf::token();
    $em = 'design' . ($pre === '' ? 'new' : 'legacy') . '@example.cz';
    $r = Pixelite\handle($dq('POST', $path, ['_csrf' => $tok, 'name' => 'Design Tester', 'email' => $em, 'project_type' => 'website', 'budget' => 'unsure', 'timeframe' => 'flexible', 'description' => 'Order sent from the ' . ($pre === '' ? 'new' : 'legacy') . ' design.', 'consent' => '1', 'template' => 'lumora'], [], '203.0.113.' . ($pre === '' ? 40 : 41), ['REQUEST_URI' => $path]));
    $sent = $dg($path . '/sent');
    check("order ($path): saved, 303 to the sent page of the SAME design, template stored, success page renders", $r->status === 303 && ($r->headers['Location'] ?? '') === $path . '/sent' && $cnt("SELECT COUNT(*) FROM orders WHERE email = '$em' AND template = 'lumora'") === 1 && $sent->status === 200 && ($pre === '' ? str_contains($sent->body, 'site.css') : str_contains($sent->body, 'style.css')));
}
$_SESSION = [];
check('customer pages exist in both designs and share one account/session layer', $dg('/en/login')->status === 200 && $dg('/legacy/en/login')->status === 200 && $dg('/en/account')->status === 302 && ($dg('/legacy/en/account')->headers['Location'] ?? '') === '/legacy/en/login');
$_SESSION = []; $r = $dg('/legacy/en/register'); $b = $r->body;
check('legacy register form posts to /legacy/en/register and keeps the disabled Dreamers placeholder', str_contains($b, 'action="/legacy/en/register"') && str_contains($b, 'auth-alt__btn" disabled'));
check('the templates catalog and its live previews work in both designs', $dg('/en/templates')->status === 200 && $dg('/legacy/en/templates')->status === 200 && $dg('/en/templates/lumora/demo')->status === 200 && str_contains($dg('/legacy/en/templates/lumora')->body, '/legacy/en/templates/lumora/demo'));

echo "swapping the default (DESIGN_DEFAULT=legacy rollback lever)\n";
Env::set('DESIGN_DEFAULT', 'legacy');
$_SESSION = [];
$a = $dg('/en/pricing')->body; $b = $dg('/new/en/pricing')->body;
check('DESIGN_DEFAULT=legacy: legacy at the normal URLs, new design under /new, labels and canonical swap accordingly', str_contains($a, '/assets/style.css') && str_contains($a, 'content="index,follow"') && str_contains($a, 'href="/new/en/pricing" rel="nofollow"') && str_contains($a, 'data-design-switch>New design</a>') && str_contains($b, '/assets/site.css') && str_contains($b, 'content="noindex,follow"') && str_contains($b, 'rel="canonical" href="https://pixelite.cz/en/pricing"'));
check('DESIGN_DEFAULT=legacy: the /legacy prefix is then not routed', $dg('/legacy/en/pricing')->status === 404);
Env::set('DESIGN_DEFAULT', 'bogus'); $_SESSION = [];
check('an invalid DESIGN_DEFAULT falls back to the new design', Design::default() === 'new' && str_contains($dg('/en/')->body, '/assets/site.css'));
Env::set('DESIGN_DEFAULT', 'new');

echo "admin in the new design\n";
$_SESSION = ['admin_at' => time(), 'admin_since' => time(), '_csrf' => 'adm-token'];
$oid = (int) Database::pdo()->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
foreach (['/admin', "/admin/orders/$oid", '/admin/customers', '/admin/projects', '/admin/projects/new', '/admin/careers', '/admin/careers/new', '/admin/templates', '/admin/templates/lumora'] as $u) {
    $r = $dg($u);
    check("admin $u: new design, navigation to every section, no legacy assets, no inline scripts", $r->status === 200 && str_contains($r->body, '/assets/site.css') && !str_contains($r->body, '/assets/style.css') && str_contains($r->body, 'href="/admin/careers"') && str_contains($r->body, 'href="/admin/projects"') && !preg_match('/<script(?![^>]*\bsrc=)[^>]*>|\sstyle="/', $r->body) && str_contains($r->body, 'noindex'));
}
$r = $dg("/admin/orders/$oid");
check('admin order page keeps the customer-link form and the Telegram retry in the new design', str_contains($r->body, '/link"') && str_contains($r->body, 'name="customer_email"') && str_contains($r->body, '/retry"'));
$_SESSION = [];
$r = $dg('/admin/login');
check('admin login: new design, noindex, no-store', $r->status === 200 && str_contains($r->body, '/assets/site.css') && str_contains($r->body, 'noindex') && str_contains($r->headers['Cache-Control'] ?? '', 'no-store'));
foreach (['/admin', '/admin/customers', '/admin/projects', '/admin/careers', '/admin/templates'] as $u) { $r = $dg($u); if ($r->status !== 302 || ($r->headers['Location'] ?? '') !== '/admin/login') { check("admin $u still redirects guests to the login", false); } }
check('every admin section still redirects guests to the staff login', true);
$r = $dg('/en/zzz-not-found');
check('404 page: new design, noindex, localized', $r->status === 404 && str_contains($r->body, '/assets/site.css') && str_contains($r->body, 'noindex'));

echo "re-running the platform feature tests against the new templates\n";
Env::set('LEADS_DATABASE_PATH', "$tmp/new2.sqlite");
Env::set('PROJECTS_DIR', "$tmp/projects-new2");
Database::reset();
$_SESSION = [];
require __DIR__ . '/features.php';
