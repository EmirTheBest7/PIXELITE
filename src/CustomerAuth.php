<?php
declare(strict_types=1);

namespace Pixelite;

/** Customer sessions. Separate session keys from the staff login (Auth), same hardened session cookie. */
final class CustomerAuth
{
    private const IDLE_SECONDS = 7200;
    private const ABSOLUTE_SECONDS = 43200;

    /** @return array<string,mixed>|null the customer row when the credentials are valid and the account is active */
    public static function attempt(string $email, string $password): ?array
    {
        $c = Customers::findByEmail($email);
        // Always run one password_verify (against a dummy hash when the account is unknown) so timing does not reveal whether the e-mail exists.
        static $dummy = null;
        $dummy ??= password_hash('pixelite-dummy-password', PASSWORD_DEFAULT);
        $ok = password_verify($password, $c['password_hash'] ?? $dummy);
        if ($c === null || !$ok || $c['status'] !== 'active') {
            return null;
        }
        if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
            Customers::touchLogin((int) $c['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        return $c;
    }

    public static function login(array $customer): void
    {
        session_regenerate_id(true);   // new session id on privilege change: prevents session fixation
        $_SESSION['customer_id'] = (int) $customer['id'];
        $_SESSION['customer_at'] = time();
        $_SESSION['customer_since'] = time();
        unset($_SESSION['_csrf']);
        Customers::touchLogin((int) $customer['id']);
    }

    /** The signed-in customer (fresh from the database, so a disabled account loses access immediately), or null. */
    public static function current(): ?array
    {
        $id = $_SESSION['customer_id'] ?? null;
        $at = $_SESSION['customer_at'] ?? null;
        $since = $_SESSION['customer_since'] ?? 0;
        if (!is_int($id) || !is_int($at) || time() - $at > self::IDLE_SECONDS || time() - (int) $since > self::ABSOLUTE_SECONDS) {
            unset($_SESSION['customer_id'], $_SESSION['customer_at'], $_SESSION['customer_since']);
            return null;
        }
        $c = Customers::find($id);
        if ($c === null || $c['status'] !== 'active') {
            unset($_SESSION['customer_id'], $_SESSION['customer_at'], $_SESSION['customer_since']);
            return null;
        }
        $_SESSION['customer_at'] = time();
        return $c;
    }

    public static function logout(): void
    {
        unset($_SESSION['customer_id'], $_SESSION['customer_at'], $_SESSION['customer_since'], $_SESSION['_csrf']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
