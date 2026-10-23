<?php
declare(strict_types=1);

namespace Pixelite;

use Pixelite\Controllers\AdminController;
use Pixelite\Controllers\OrderController;
use Pixelite\Controllers\PageController;
use Pixelite\Controllers\SeoController;
use Pixelite\Controllers\TemplatesController;
use Pixelite\Controllers\AdminTemplatesController;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Pixelite\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require __DIR__ . '/helpers.php';

Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set('Europe/Prague');
mb_internal_encoding('UTF-8');

function buildRouter(): Router
{
    $router = new Router();
    $page = new PageController();
    $order = new OrderController();
    $admin = new AdminController();
    $seo = new SeoController();
    $templates = new TemplatesController();
    $adminTemplates = new AdminTemplatesController();
    $account = new \Pixelite\Controllers\AccountController();
    $careers = new \Pixelite\Controllers\AdminCareersController();
    $projects = new \Pixelite\Controllers\AdminProjectsController();
    $customers = new \Pixelite\Controllers\AdminCustomersController();

    $router->add('GET', '/{lang}', fn() => $page->home());
    $router->add('GET', '/{lang}/pricing', fn() => $page->pricing());
    $router->add('GET', '/{lang}/process', fn() => $page->process());
    $router->add('GET', '/{lang}/contact', fn(Request $r) => $page->contact($r));
    $router->add('GET', '/{lang}/privacy', fn() => $page->privacy());
    $router->add('GET', '/{lang}/cookies', fn(Request $r) => $page->cookies($r));
    $router->add('GET', '/{lang}/terms', fn() => $page->terms());
    $router->add('GET', '/{lang}/careers', fn() => $page->careers());
    $router->add('GET', '/{lang}/team', fn() => $page->team());
    $router->add('GET', '/{lang}/portfolio', fn() => $page->portfolio());
    $router->add('GET', '/{lang}/login', fn(Request $r) => $account->loginForm($r));
    $router->add('POST', '/{lang}/login', fn(Request $r) => $account->login($r));
    $router->add('GET', '/{lang}/register', fn(Request $r) => $account->registerForm($r));
    $router->add('POST', '/{lang}/register', fn(Request $r) => $account->register($r));
    $router->add('POST', '/{lang}/logout', fn(Request $r) => $account->logout($r));
    $router->add('GET', '/{lang}/account', fn(Request $r) => $account->account($r));
    $router->add('POST', '/{lang}/account', fn(Request $r) => $account->updateProfile($r));
    $router->add('GET', '/{lang}/templates', fn(Request $r) => $templates->index($r));
    $router->add('GET', '/{lang}/templates/{slug}', fn(Request $r, array $p) => $templates->show($r, $p));
    $router->add('GET', '/{lang}/templates/{slug}/demo', fn(Request $r, array $p) => $templates->demo($r, $p));
    $router->add('GET', '/{lang}/order', fn(Request $r) => $order->show($r));
    $router->add('POST', '/{lang}/order', fn(Request $r) => $order->submit($r));
    $router->add('GET', '/{lang}/order/sent', fn(Request $r) => $order->sent($r));

    $router->add('GET', '/robots.txt', fn() => $seo->robots());
    $router->add('GET', '/sitemap.xml', fn() => $seo->sitemap());

    $router->add('GET', '/admin/login', fn(Request $r) => $admin->loginForm($r));
    $router->add('POST', '/admin/login', fn(Request $r) => $admin->login($r));
    $router->add('POST', '/admin/logout', fn(Request $r) => $admin->logout($r));
    $router->add('GET', '/admin', fn(Request $r) => $admin->index($r));
    $router->add('GET', '/admin/templates', fn(Request $r) => $adminTemplates->index($r));
    $router->add('POST', '/admin/templates/import', fn(Request $r) => $adminTemplates->import($r));
    $router->add('GET', '/admin/templates/{slug}', fn(Request $r, array $p) => $adminTemplates->show($r, $p));
    $router->add('POST', '/admin/templates/{slug}', fn(Request $r, array $p) => $adminTemplates->save($r, $p));
    $router->add('POST', '/admin/orders/{id}/link', fn(Request $r, array $p) => $customers->linkOrder($r, $p));
    $router->add('GET', '/admin/customers', fn(Request $r) => $customers->index($r));
    $router->add('GET', '/admin/customers/{id}', fn(Request $r, array $p) => $customers->show($r, $p));
    $router->add('POST', '/admin/customers/{id}/status', fn(Request $r, array $p) => $customers->status($r, $p));
    $router->add('GET', '/admin/careers', fn(Request $r) => $careers->index($r));
    $router->add('GET', '/admin/careers/new', fn(Request $r) => $careers->create($r));
    $router->add('POST', '/admin/careers', fn(Request $r) => $careers->store($r));
    $router->add('GET', '/admin/careers/{id}', fn(Request $r, array $p) => $careers->edit($r, $p));
    $router->add('POST', '/admin/careers/{id}', fn(Request $r, array $p) => $careers->update($r, $p));
    $router->add('POST', '/admin/careers/{id}/publish', fn(Request $r, array $p) => $careers->publish($r, $p));
    $router->add('POST', '/admin/careers/{id}/unpublish', fn(Request $r, array $p) => $careers->unpublish($r, $p));
    $router->add('GET', '/admin/careers/{id}/delete', fn(Request $r, array $p) => $careers->confirmDelete($r, $p));
    $router->add('POST', '/admin/careers/{id}/delete', fn(Request $r, array $p) => $careers->delete($r, $p));
    $router->add('GET', '/admin/projects', fn(Request $r) => $projects->index($r));
    $router->add('GET', '/admin/projects/new', fn(Request $r) => $projects->create($r));
    $router->add('POST', '/admin/projects', fn(Request $r) => $projects->store($r));
    $router->add('GET', '/admin/projects/{id}', fn(Request $r, array $p) => $projects->show($r, $p));
    $router->add('POST', '/admin/projects/{id}', fn(Request $r, array $p) => $projects->update($r, $p));
    $router->add('POST', '/admin/projects/{id}/upload', fn(Request $r, array $p) => $projects->upload($r, $p));
    $router->add('POST', '/admin/projects/{id}/access', fn(Request $r, array $p) => $projects->access($r, $p));
    $router->add('POST', '/admin/projects/{id}/token', fn(Request $r, array $p) => $projects->regenerate($r, $p));
    $router->add('GET', '/admin/projects/{id}/delete', fn(Request $r, array $p) => $projects->confirmDelete($r, $p));
    $router->add('POST', '/admin/projects/{id}/delete', fn(Request $r, array $p) => $projects->delete($r, $p));
    $router->add('GET', '/admin/orders/{id}', fn(Request $r, array $p) => $admin->show($r, $p));
    $router->add('POST', '/admin/orders/{id}/retry', fn(Request $r, array $p) => $admin->retry($r, $p));

    // PUBLIC site builder (src/Editor/Builder/Anon/): no account, temporary per-visitor workspaces. On by default; BUILDER_PUBLIC=false switches it (routes + links) off.
    if (\Pixelite\Editor\Builder\Anon\AnonWorkspaces::enabled()) {
        $bpage = new \Pixelite\Controllers\BuilderPageController();
        $pub = new \Pixelite\Editor\Builder\Anon\PublicBuilderController();
        $router->add('GET', '/{lang}/builder', fn(Request $r) => $bpage->landing($r));
        $router->add('POST', '/{lang}/builder', fn(Request $r) => $bpage->start($r));
        $router->add('GET', '/builder/w', fn(Request $r, array $p) => $pub->shell($r, $p));
        $router->add('GET', '/builder/w/site', fn(Request $r, array $p) => $pub->site($r, $p));
        $router->add('POST', '/builder/w/pages', fn(Request $r, array $p) => $pub->createPage($r, $p));
        $router->add('GET', '/builder/w/pages/{slug}/canvas', fn(Request $r, array $p) => $pub->canvas($r, $p));
        $router->add('GET', '/builder/w/pages/{slug}/preview', fn(Request $r, array $p) => $pub->preview($r, $p));
        $router->add('POST', '/builder/w/pages/{slug}', fn(Request $r, array $p) => $pub->savePage($r, $p));
        $router->add('POST', '/builder/w/pages/{slug}/delete', fn(Request $r, array $p) => $pub->deletePage($r, $p));
        $router->add('GET', '/builder/w/media', fn(Request $r, array $p) => $pub->mediaList($r, $p));
        $router->add('POST', '/builder/w/media', fn(Request $r, array $p) => $pub->uploadMedia($r, $p));
        $router->add('GET', '/builder/w/media/{slug}', fn(Request $r, array $p) => $pub->mediaFile($r, $p));
        $router->add('POST', '/builder/w/media/{slug}/delete', fn(Request $r, array $p) => $pub->mediaDelete($r, $p));
    }

    // EXPERIMENTAL visual editor (src/Editor/): the routes only exist while EDITOR_ENABLED=true; otherwise every /editor/… URL is a plain 404.
    if (\Pixelite\Editor\Editor::enabled()) {
        $editor = new \Pixelite\Editor\EditorController();
        $router->add('GET', '/editor/projects/{id}', fn(Request $r, array $p) => $editor->shell($r, $p));
        $router->add('GET', '/editor/projects/{id}/canvas', fn(Request $r, array $p) => $editor->canvas($r, $p));
        $router->add('GET', '/editor/projects/{id}/preview', fn(Request $r, array $p) => $editor->preview($r, $p));
        $router->add('GET', '/editor/projects/{id}/theme.css', fn(Request $r, array $p) => $editor->themeCss($r, $p));
        $router->add('GET', '/editor/projects/{id}/draft', fn(Request $r, array $p) => $editor->draft($r, $p));
        $router->add('POST', '/editor/projects/{id}/draft', fn(Request $r, array $p) => $editor->saveDraft($r, $p));
        $router->add('POST', '/editor/projects/{id}/media', fn(Request $r, array $p) => $editor->uploadMedia($r, $p));
        $router->add('GET', '/editor/projects/{id}/media/{slug}', fn(Request $r, array $p) => $editor->media($r, $p));
        $builder = new \Pixelite\Editor\Builder\BuilderController();
        $router->add('GET', '/editor/projects/{id}/builder', fn(Request $r, array $p) => $builder->shell($r, $p));
        $router->add('GET', '/editor/projects/{id}/builder/site', fn(Request $r, array $p) => $builder->site($r, $p));
        $router->add('POST', '/editor/projects/{id}/builder/pages', fn(Request $r, array $p) => $builder->createPage($r, $p));
        $router->add('GET', '/editor/projects/{id}/builder/pages/{slug}/canvas', fn(Request $r, array $p) => $builder->canvas($r, $p));
        $router->add('GET', '/editor/projects/{id}/builder/pages/{slug}/preview', fn(Request $r, array $p) => $builder->preview($r, $p));
        $router->add('POST', '/editor/projects/{id}/builder/pages/{slug}', fn(Request $r, array $p) => $builder->savePage($r, $p));
        $router->add('POST', '/editor/projects/{id}/builder/pages/{slug}/delete', fn(Request $r, array $p) => $builder->deletePage($r, $p));
        $router->add('GET', '/editor/projects/{id}/builder/media', fn(Request $r, array $p) => $builder->mediaList($r, $p));
        $router->add('POST', '/editor/projects/{id}/builder/media/{slug}/delete', fn(Request $r, array $p) => $builder->mediaDelete($r, $p));
    }

    // Bare "/" picks a language: remembered choice (cookie) > browser preference > APP_LOCALE.
    $router->add('GET', '/', function (Request $r): Response {
        $pick = $_COOKIE['pixelite_lang'] ?? '';
        if (!in_array($pick, I18n::LOCALES, true)) {
            $pick = bestLocale($r->header('Accept-Language'));
        }
        return new Response(302, '', ['Location' => url('', $pick), 'Vary' => 'Cookie, Accept-Language']);
    });
    return $router;
}

/** Picks the supported locale with the highest q-value in Accept-Language (ties: header order); else APP_LOCALE. */
function bestLocale(string $header): string
{
    $best = I18n::default();
    $bestQ = 0.0;
    foreach (explode(',', $header) as $part) {
        if (!preg_match('/^\s*([a-zA-Z]{1,8})(?:-[a-zA-Z0-9-]+)?\s*(?:;\s*q=([0-9.]+))?\s*$/', $part, $m)) {
            continue;
        }
        $lang = strtolower($m[1]);
        $q = isset($m[2]) ? (float) $m[2] : 1.0;
        if ($q > $bestQ && in_array($lang, I18n::LOCALES, true)) {
            [$best, $bestQ] = [$lang, $q];
        }
    }
    return $best;
}

function notFound(Request $r): Response
{
    I18n::set(preg_match('#^/(' . implode('|', I18n::LOCALES) . ')(/|$)#', $r->path, $m) ? $m[1] : I18n::default());
    return Response::html(View::render('errors/404', [
        'meta' => ['page' => '404', 'path' => '', 'title' => (string) t('meta.404.title'), 'description' => '', 'robots' => 'noindex,nofollow'],
        'locale' => I18n::locale(),
    ], 'layout'), 404);
}

function securityHeaders(Response $resp, Request $r): void
{
    $resp->headers += [
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Frame-Options' => 'DENY',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];
    if ($r->isHttps()) {
        $resp->headers['Strict-Transport-Security'] = 'max-age=31536000';
    }
    // Anything that touches a session or admin must not be cached.
    if (session_status() === PHP_SESSION_ACTIVE || str_starts_with($r->path, '/admin')) {
        $resp->headers['Cache-Control'] = 'no-store';
    }
}

/**
 * One URL per page: www → apex host, `/en` → `/en/`, `/en/pricing/` → `/en/pricing` (301, query kept).
 * Only for GET/HEAD in a real HTTP request (REQUEST_URI present); never touches /admin or POSTs.
 */
function canonicalRedirect(Request $r): ?Response
{
    $uri = $r->rawUri();
    if ($uri === '' || !in_array($r->method, ['GET', 'HEAD'], true)) {
        return null;
    }
    $path = (string) parse_url($uri, PHP_URL_PATH);
    $query = (string) parse_url($uri, PHP_URL_QUERY);
    $pre = '';   // /legacy or /new keeps its prefix
    if (preg_match('#^/(?:new|legacy)(?=/|$)#', $path, $pm)) {
        $pre = $pm[0];
        $path = substr($path, strlen($pre)) ?: '/';
    }
    $target = $path;
    if (preg_match('#^/(?:' . implode('|', I18n::LOCALES) . ')$#', $path)) {
        $target = $path . '/';
    } elseif (preg_match('#^/(?:' . implode('|', I18n::LOCALES) . ')/.+/$#', $path)) {
        $target = rtrim($path, '/');
    }
    $origin = rtrim(Env::get('APP_URL', 'https://pixelite.cz'), '/');
    $apexHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $wrongHost = $apexHost !== '' && $r->host() === 'www.' . $apexHost;
    if ($target === $path && !$wrongHost) {
        return null;
    }
    $base = $wrongHost ? $origin : '';
    return new Response(301, '', ['Location' => $base . $pre . $target . ($query !== '' ? '?' . $query : '')]);
}

function handle(Request $r): Response
{
    Request::$current = $r;
    I18n::set(I18n::default());
    // Client preview host: a separate origin that serves ONLY /p/<token>/… (no router, no session, no cookies). On the main host /p/… does not exist.
    if (Preview::isPreviewHost($r)) {
        return Preview::handle($r);
    }
    if (str_starts_with($r->path, '/p/')) {
        return notFound($r);
    }
    // The other design lives under its own prefix (/legacy/… or /new/…) and only for public pages; staff and utility routes exist once, in the default design.
    Design::set(Design::default());
    if (preg_match('#^/(new|legacy)(?=/|$)#', $r->path, $pm) && $pm[1] === Design::alternate()) {
        $rest = substr($r->path, strlen($pm[0])) ?: '/';
        if ($rest !== '/' && !preg_match('#^/(?:' . implode('|', I18n::LOCALES) . ')(?:/|$)#', $rest)) {
            return notFound($r);
        }
        Design::set($pm[1]);
        $r = $r->withPath($rest);
    }
    if (($redirect = canonicalRedirect($r)) !== null) {
        securityHeaders($redirect, $r);
        return $redirect;
    }
    try {
        $resp = buildRouter()->dispatch($r) ?? notFound($r);
    } catch (\Throwable $ex) {
        // Full detail goes to the log only; visitors get a generic page.
        Logger::error('unhandled exception', ['type' => get_class($ex), 'msg' => $ex->getMessage(), 'at' => basename($ex->getFile()) . ':' . $ex->getLine()]);
        $resp = Response::html(View::render('errors/500', [
            'meta' => ['page' => '500', 'path' => '', 'title' => (string) t('meta.500.title'), 'description' => '', 'robots' => 'noindex,nofollow'],
            'locale' => I18n::locale(),
        ], 'layout'), 500);
    }
    securityHeaders($resp, $r);
    return $resp;
}
