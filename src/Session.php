<?php
declare(strict_types=1);

namespace Pixelite;

/** Sessions start lazily (order form + admin only), so plain visitors never receive a cookie. */
final class Session
{
    /**
     * Over HTTPS the cookie carries the "__Host-" prefix: browsers then refuse it unless it is Secure, Path=/ and has NO Domain attribute,
     * and a sibling (sub)domain – e.g. a client-preview host – cannot overwrite or shadow it ("cookie tossing").
     */
    public static function cookieName(Request $r): string
    {
        return $r->isHttps() ? '__Host-pixelite' : 'pixelite';
    }

    public static function start(Request $r): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(self::cookieName($r));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $r->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.gc_maxlifetime', '43200');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }
}
