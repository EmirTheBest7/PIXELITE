<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Browser previews of client projects (/p/<token>/…). They contain ARBITRARY HTML/JS, so they are only ever served from a DIFFERENT ORIGIN
 * than Pixelite itself (PREVIEW_ORIGIN, e.g. https://preview.pixelite.cz): no Pixelite cookie, session, admin page or customer page shares that origin.
 *  - Disabled unless PREVIEW_ORIGIN is a valid origin with a host different from APP_URL's host.
 *  - On the preview host nothing else is reachable (no router, no session, no cookies, no admin/login); on the main host /p/… is 404.
 *  - Each response carries its own restrictive header set (see headers()).
 */
final class Preview
{
    public static function origin(): string
    {
        $o = rtrim(Env::get('PREVIEW_ORIGIN'), '/');
        $p = parse_url($o);
        if ($o === '' || !is_array($p) || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host']) || isset($p['path']) || isset($p['query']) || isset($p['user'])) {
            return '';
        }
        $app = parse_url(Env::get('APP_URL', 'https://pixelite.cz'));
        // Same host as the main site = same cookies: refuse. (A different port alone is NOT isolation, cookies ignore ports.)
        if (strtolower((string) ($app['host'] ?? '')) === strtolower($p['host'])) {
            return '';
        }
        return $o;
    }

    public static function enabled(): bool
    {
        return self::origin() !== '';
    }

    /** "host" or "host:port" as it appears in the Host header. */
    private static function authority(): string
    {
        $p = parse_url(self::origin());
        $default = ($p['scheme'] ?? '') === 'https' ? 443 : 80;
        $port = isset($p['port']) && (int) $p['port'] !== $default ? ':' . $p['port'] : '';
        return strtolower($p['host']) . $port;
    }

    /** True when the preview host is a sibling/child of the main host's registrable domain (e.g. preview.pixelite.cz): isolated by origin, but cookies for the parent domain can still be set from it. */
    public static function sharesSite(): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $reg = static fn(string $h): string => implode('.', array_slice(explode('.', strtolower($h)), -2));
        return $reg((string) parse_url(self::origin(), PHP_URL_HOST)) === $reg((string) parse_url(Env::get('APP_URL', 'https://pixelite.cz'), PHP_URL_HOST));
    }

    public static function isPreviewHost(Request $r): bool
    {
        return self::enabled() && strtolower($r->header('Host')) === self::authority();
    }

    public static function url(array $project): string
    {
        return self::enabled() ? self::origin() . '/p/' . $project['token'] . '/' : '';
    }

    public static function handle(Request $r): Response
    {
        if (!in_array($r->method, ['GET', 'HEAD'], true)) {
            return self::plain(405, 'Method not allowed');
        }
        if (!preg_match('#^/p/([A-Za-z0-9_-]{43})(/.*)?$#', $r->path, $m)) {
            return self::plain(404, 'Not found');
        }
        $bucket = 'preview-miss:' . RateLimiter::clientKey($r);
        if (RateLimiter::blocked($bucket, 60, 300)) {
            return self::plain(429, 'Too many requests');
        }
        $project = Projects::byToken($m[1]);
        if ($project === null) {
            RateLimiter::record($bucket);   // brute-force guessing is throttled; 256-bit tokens make it hopeless anyway
            return self::plain(404, 'Not found');
        }
        $rest = $m[2] ?? '';
        // Request normalises away trailing slashes, so the raw URI decides whether this was /p/<token> (redirect) or /p/<token>/ (serve).
        $raw = $r->rawUri() !== '' ? (string) parse_url($r->rawUri(), PHP_URL_PATH) : $r->path;
        if ($rest === '' && !str_ends_with($raw, '/')) {
            return new Response(302, '', ['Location' => '/p/' . $m[1] . '/'] + self::headers(false));   // relative links in the site need the trailing slash
        }
        $rel = ltrim($rest, '/');
        if ($rel === '') {
            $rel = $project['entry'];
        } elseif (str_ends_with($rest, '/')) {
            $rel .= 'index.html';
        }
        $file = ProjectStorage::resolve($project['uid'], $rel);
        if ($file === null && !str_contains(basename($rel), '.')) {
            $file = ProjectStorage::resolve($project['uid'], rtrim($rel, '/') . '/index.html');   // /about -> about/index.html
        }
        $type = $file !== null ? ProjectStorage::mime($file) : null;
        if ($file === null || $type === null) {
            return self::plain(404, 'Not found');
        }
        return new Response(200, '', ['Content-Type' => $type, 'Content-Length' => (string) filesize($file)] + self::headers(true), $file);
    }

    private static function plain(int $status, string $text): Response
    {
        return new Response($status, $text, ['Content-Type' => 'text/plain; charset=utf-8'] + self::headers(false));
    }

    /** @return array<string,string> */
    private static function headers(bool $content): array
    {
        $h = [
            // Client sites may use inline scripts/styles, but may only talk to THEIR OWN files: no external hosts, no framing of or by other origins,
            // no form posts anywhere, no workers (no service worker persistence), no plugins.
            'Content-Security-Policy' => "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
                . "media-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-src 'self'; worker-src 'none'; object-src 'none'; "
                . "base-uri 'self'; form-action 'none'; frame-ancestors 'none'; manifest-src 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',                 // the token is in the URL: never leak it through Referer
            'Cross-Origin-Opener-Policy' => 'same-origin',      // no window.opener link between the preview and the page that opened it
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=()',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',   // hygiene only: the token is the access control
            'Cache-Control' => 'private, no-store',
        ];
        if (str_starts_with(self::origin(), 'https://')) {
            $h['Strict-Transport-Security'] = 'max-age=31536000';
        }
        return $h;
    }
}
