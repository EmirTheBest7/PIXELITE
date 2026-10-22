<?php
declare(strict_types=1);

namespace Pixelite;

use PDOException;

/** Customer accounts (basic CRM). Passwords are hashed with password_hash(); the hash never leaves this class' callers' templates. */
final class Customers
{
    public const PASSWORD_MIN = 10;
    public const PASSWORD_MAX_BYTES = 72;   // bcrypt (PASSWORD_DEFAULT) ignores everything after 72 bytes – refuse instead of silently truncating
    public const MAX = ['name' => 100, 'company' => 120, 'email' => 254, 'phone' => 30];
    public const STATUSES = ['active', 'disabled'];

    private static function single(string $s): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '');
    }

    /** @param array<string,string> $in @return array{0: array<string,string>, 1: array<string,string>} [clean, errors as field => lang key under auth.errors] */
    public static function validateRegistration(array $in, bool $consent): array
    {
        $clean = [
            'name' => self::single($in['name'] ?? ''),
            'email' => strtolower(self::single($in['email'] ?? '')),
            'company' => self::single($in['company'] ?? ''),
            'phone' => self::single($in['phone'] ?? ''),
            'password' => (string) ($in['password'] ?? ''),
        ];
        $e = self::validateProfileFields($clean);
        if ($clean['email'] === '') {
            $e['email'] = 'email_required';
        } elseif (mb_strlen($clean['email']) > self::MAX['email'] || !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'email_invalid';
        }
        if (strlen($clean['password']) < self::PASSWORD_MIN) {
            $e['password'] = 'password_short';
        } elseif (strlen($clean['password']) > self::PASSWORD_MAX_BYTES) {
            $e['password'] = 'password_long';
        } elseif (strtolower($clean['password']) === $clean['email']) {
            $e['password'] = 'password_email';
        } elseif (($in['password2'] ?? '') !== $clean['password']) {
            $e['password2'] = 'password_mismatch';
        }
        if (!$consent) {
            $e['consent'] = 'consent_required';
        }
        return [$clean, $e];
    }

    /** @param array<string,string> $in @return array{0: array<string,string>, 1: array<string,string>} */
    public static function validateProfile(array $in): array
    {
        $clean = ['name' => self::single($in['name'] ?? ''), 'company' => self::single($in['company'] ?? ''), 'phone' => self::single($in['phone'] ?? '')];
        return [$clean, self::validateProfileFields($clean)];
    }

    /** @param array<string,string> $c @return array<string,string> */
    private static function validateProfileFields(array $c): array
    {
        $e = [];
        if (mb_strlen($c['name']) < 2) {
            $e['name'] = 'name_required';
        } elseif (mb_strlen($c['name']) > self::MAX['name']) {
            $e['name'] = 'too_long';
        }
        if (mb_strlen($c['company']) > self::MAX['company']) {
            $e['company'] = 'too_long';
        }
        if ($c['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-\/.]{6,30}$/', $c['phone'])) {
            $e['phone'] = 'phone_invalid';
        }
        return $e;
    }

    /** @param array<string,string> $clean @return int new id, or 0 when the e-mail address is already registered */
    public static function create(array $clean, string $locale): int
    {
        $now = gmdate('c');
        try {
            Database::pdo()->prepare('INSERT INTO customers (email, password_hash, name, company, phone, locale, status, created_at, consent_at) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$clean['email'], password_hash($clean['password'], PASSWORD_DEFAULT), $clean['name'], $clean['company'], $clean['phone'], $locale, 'active', $now, $now]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return 0;
            }
            throw $e;
        }
        return (int) Database::pdo()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $s = Database::pdo()->prepare('SELECT * FROM customers WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $s = Database::pdo()->prepare('SELECT * FROM customers WHERE email = ?');
        $s->execute([strtolower(trim($email))]);
        return $s->fetch() ?: null;
    }

    /** @param array<string,string> $clean name, company, phone */
    public static function updateProfile(int $id, array $clean): void
    {
        Database::pdo()->prepare('UPDATE customers SET name = ?, company = ?, phone = ? WHERE id = ?')->execute([$clean['name'], $clean['company'], $clean['phone'], $id]);
    }

    public static function touchLogin(int $id, string $newHash = ''): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE customers SET last_login_at = ? WHERE id = ?')->execute([gmdate('c'), $id]);
        if ($newHash !== '') {
            $pdo->prepare('UPDATE customers SET password_hash = ? WHERE id = ?')->execute([$newHash, $id]);
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        if (in_array($status, self::STATUSES, true)) {
            Database::pdo()->prepare('UPDATE customers SET status = ? WHERE id = ?')->execute([$status, $id]);
        }
    }

    /** @return list<array<string,mixed>> without password hashes */
    public static function latest(int $limit = 100, int $offset = 0): array
    {
        $s = Database::pdo()->prepare('SELECT id, email, name, company, phone, locale, status, created_at, last_login_at FROM customers ORDER BY id DESC LIMIT ? OFFSET ?');
        $s->execute([$limit, $offset]);
        return $s->fetchAll();
    }

    /** The customer's OWN orders (explicit customer_id link only – never matched by e-mail). */
    public static function orders(int $customerId): array
    {
        $s = Database::pdo()->prepare('SELECT id, created_at, locale, project_type, package, package_name, package_price, template, template_name FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 100');
        $s->execute([$customerId]);
        return $s->fetchAll();
    }

    /** Link (or unlink with 0) an order to a customer. Administrator action only. */
    public static function linkOrder(int $orderId, int $customerId): void
    {
        Database::pdo()->prepare('UPDATE orders SET customer_id = ? WHERE id = ?')->execute([$customerId > 0 ? $customerId : null, $orderId]);
    }
}
