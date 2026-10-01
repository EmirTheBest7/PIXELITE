<?php
declare(strict_types=1);

namespace Pixelite;

/** Single staff account from env (ADMIN_EMAIL + ADMIN_PASSWORD_HASH). No registration, no customer accounts. */
final class Auth
{
    private const IDLE_SECONDS = 7200;

    public static function enabled(): bool
    {
        return Env::get('ADMIN_EMAIL') !== '' && Env::get('ADMIN_PASSWORD_HASH') !== '';
    }

    public static function attempt(string $email, string $password): bool
    {
        if (!self::enabled()) {
            return false;
        }
        // Always run password_verify so response time doesn't reveal whether the email matched.
        $okPass = password_verify($password, Env::get('ADMIN_PASSWORD_HASH'));
        $okMail = hash_equals(strtolower(Env::get('ADMIN_EMAIL')), strtolower(trim($email)));
        return $okPass && $okMail;
    }

    public static function login(): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_at'] = time();
        $_SESSION['admin_since'] = time();
        unset($_SESSION['_csrf']);
    }

    public static function check(): bool
    {
        $at = $_SESSION['admin_at'] ?? null;
        $since = $_SESSION['admin_since'] ?? 0;
        if (!is_int($at) || time() - $at > self::IDLE_SECONDS || time() - (int) $since > 43200) {
            return false;
        }
        $_SESSION['admin_at'] = time();
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
