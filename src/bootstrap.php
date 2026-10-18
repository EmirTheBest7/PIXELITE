<?php
declare(strict_types=1);

namespace Pixelite;

use Pixelite\Controllers\AdminController;
use Pixelite\Controllers\OrderController;
use Pixelite\Controllers\PageController;
use Pixelite\Controllers\SeoController;

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

    $router->add('GET', '/{lang}', fn() => $page->home());
    $router->add('GET', '/{lang}/pricing', fn() => $page->pricing());
    $router->add('GET', '/{lang}/process', fn() => $page->process());
    $router->add('GET', '/{lang}/contact', fn() => $page->contact());
    $router->add('GET', '/{lang}/privacy', fn() => $page->privacy());
    $router->add('GET', '/{lang}/cookies', fn() => $page->cookies());
    $router->add('GET', '/{lang}/terms', fn() => $page->terms());
    $router->add('GET', '/{lang}/portfolio', fn() => $page->portfolio());
    $router->add('GET', '/{lang}/order', fn(Request $r) => $order->show($r));
    $router->add('POST', '/{lang}/order', fn(Request $r) => $order->submit($r));
    $router->add('GET', '/{lang}/order/sent', fn(Request $r) => $order->sent($r));

    $router->add('GET', '/robots.txt', fn() => $seo->robots());
    $router->add('GET', '/sitemap.xml', fn() => $seo->sitemap());

    $router->add('GET', '/admin/login', fn(Request $r) => $admin->loginForm($r));
    $router->add('POST', '/admin/login', fn(Request $r) => $admin->login($r));
    $router->add('POST', '/admin/logout', fn(Request $r) => $admin->logout($r));
    $router->add('GET', '/admin', fn(Request $r) => $admin->index($r));
    $router->add('GET', '/admin/orders/{id}', fn(Request $r, array $p) => $admin->show($r, $p));
    $router->add('POST', '/admin/orders/{id}/retry', fn(Request $r, array $p) => $admin->retry($r, $p));

    // Bare "/" picks a language: remembered choice (cookie) > browser preference > APP_LOCALE.
    $router->add('GET', '/', function (Request $r): Response {
        $pick = $_COOKIE['pixelite_lang'] ?? '';
        if (!in_array($pick, I18n::LOCALES, true)) {
            $pick = bestLocale($r->header('Accept-Language'));
        }
        return new Response(302, '', ['Location' => "/$pick/", 'Vary' => 'Cookie, Accept-Language']);
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
    I18n::set(preg_match('#^/(en|cs)(/|$)#', $r->path, $m) ? $m[1] : I18n::default());
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
    $target = $path;
    if (preg_match('#^/(?:en|cs)$#', $path)) {
        $target = $path . '/';
    } elseif (preg_match('#^/(?:en|cs)/.+/$#', $path)) {
        $target = rtrim($path, '/');
    }
    $origin = rtrim(Env::get('APP_URL', 'https://pixelite.cz'), '/');
    $apexHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $wrongHost = $apexHost !== '' && $r->host() === 'www.' . $apexHost;
    if ($target === $path && !$wrongHost) {
        return null;
    }
    $base = $wrongHost ? $origin : '';
    return new Response(301, '', ['Location' => $base . $target . ($query !== '' ? '?' . $query : '')]);
}

function handle(Request $r): Response
{
    I18n::set(I18n::default());
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
