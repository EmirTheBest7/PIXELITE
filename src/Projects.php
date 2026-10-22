<?php
declare(strict_types=1);

namespace Pixelite;

/** Client projects with a shareable preview link. The preview token is a bearer credential (256 random bits). Metadata only – files live in ProjectStorage. */
final class Projects
{
    public const STATUSES = ['draft', 'in_review', 'approved', 'archived'];
    public const MAX_NAME = 120;
    public const MAX_NOTES = 4000;

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');   // 43 URL-safe characters
    }

    public static function validToken(string $t): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $t);
    }

    /** @return array{0: array<string,mixed>, 1: list<string>} */
    public static function validate(array $post): array
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($post['name'] ?? '')) ?? '');
        $notes = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", (string) ($post['notes'] ?? ''))) ?? '');
        $status = (string) ($post['status'] ?? 'draft');
        $order = trim((string) ($post['order_id'] ?? ''));
        $cust = trim((string) ($post['customer_email'] ?? ''));
        $errors = [];
        if ($name === '' || mb_strlen($name) > self::MAX_NAME) { $errors[] = 'The project name is required (max ' . self::MAX_NAME . ' characters).'; }
        if (mb_strlen($notes) > self::MAX_NOTES) { $errors[] = 'Internal notes are limited to ' . self::MAX_NOTES . ' characters.'; }
        if (!in_array($status, self::STATUSES, true)) { $errors[] = 'Unknown status.'; $status = 'draft'; }
        $orderId = null;
        if ($order !== '') {
            if (!ctype_digit($order) || (new OrderRepository())->find((int) $order) === null) { $errors[] = 'That order does not exist.'; } else { $orderId = (int) $order; }
        }
        $customerId = null;
        if ($cust !== '') {
            $c = Customers::findByEmail($cust);
            if ($c === null) { $errors[] = 'No customer account has that e-mail address.'; } else { $customerId = (int) $c['id']; }
        }
        return [['name' => $name, 'notes' => $notes, 'status' => $status, 'order_id' => $orderId, 'customer_id' => $customerId], $errors];
    }

    public static function create(array $clean): array
    {
        $now = gmdate('c');
        $uid = bin2hex(random_bytes(8));
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO projects (uid, name, status, access_enabled, token, token_rotated_at, notes, order_id, customer_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$uid, $clean['name'], $clean['status'], 0, self::newToken(), $now, $clean['notes'], $clean['order_id'], $clean['customer_id'], $now, $now]);
        return self::find((int) $pdo->lastInsertId());
    }

    public static function update(int $id, array $clean): void
    {
        Database::pdo()->prepare('UPDATE projects SET name = ?, status = ?, notes = ?, order_id = ?, customer_id = ?, updated_at = ? WHERE id = ?')
            ->execute([$clean['name'], $clean['status'], $clean['notes'], $clean['order_id'], $clean['customer_id'], gmdate('c'), $id]);
    }

    public static function find(int $id): ?array
    {
        $s = Database::pdo()->prepare('SELECT * FROM projects WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /** Only projects whose access is switched on. Constant-time comparison after the lookup. */
    public static function byToken(string $token): ?array
    {
        if (!self::validToken($token)) {
            return null;
        }
        $s = Database::pdo()->prepare('SELECT * FROM projects WHERE token = ? AND access_enabled = 1');
        $s->execute([$token]);
        $p = $s->fetch();
        return $p && hash_equals((string) $p['token'], $token) ? $p : null;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM projects ORDER BY id DESC')->fetchAll();
    }

    /** The customer's OWN projects only. */
    public static function forCustomer(int $customerId): array
    {
        $s = Database::pdo()->prepare('SELECT * FROM projects WHERE customer_id = ? ORDER BY id DESC');
        $s->execute([$customerId]);
        return $s->fetchAll();
    }

    public static function setAccess(int $id, bool $on): void
    {
        Database::pdo()->prepare('UPDATE projects SET access_enabled = ?, updated_at = ? WHERE id = ?')->execute([$on ? 1 : 0, gmdate('c'), $id]);
    }

    /** Old links stop working immediately. */
    public static function regenerateToken(int $id): void
    {
        $now = gmdate('c');
        Database::pdo()->prepare('UPDATE projects SET token = ?, token_rotated_at = ?, updated_at = ? WHERE id = ?')->execute([self::newToken(), $now, $now, $id]);
    }

    public static function refreshStats(array $p): void
    {
        $inv = ProjectStorage::inventory($p['uid']);
        Database::pdo()->prepare('UPDATE projects SET file_count = ?, total_bytes = ?, entry = ?, updated_at = ? WHERE id = ?')
            ->execute([count($inv), array_sum(array_column($inv, 'size')), ProjectStorage::guessEntry($p['uid']), gmdate('c'), $p['id']]);
    }

    public static function delete(array $p): void
    {
        ProjectStorage::remove($p['uid']);
        Database::pdo()->prepare('DELETE FROM projects WHERE id = ?')->execute([$p['id']]);
    }
}
