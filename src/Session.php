<?php
declare(strict_types=1);

namespace Pixelite;

/** Sessions start lazily (order form + admin only), so plain visitors never receive a cookie. */
final class Session
{
    public static function start(Request $r): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('pixelite');
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
