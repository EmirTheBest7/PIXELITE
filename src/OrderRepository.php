<?php
declare(strict_types=1);

namespace Pixelite;

final class OrderRepository
{
    /** @param array<string,string> $o validated order */
    public function create(array $o, string $locale): int
    {
        $now = gmdate('c');
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO orders
            (created_at, locale, name, company, email, phone, project_type, package, package_name, package_price, price_vat_mode, budget, timeframe, description, consent_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$now, $locale, $o['name'], $o['company'], $o['email'], $o['phone'],
                $o['project_type'], $o['package'] ?? '', $o['package_name'] ?? '', $o['package_price'] ?? '', $o['price_vat_mode'] ?? '', $o['budget'], $o['timeframe'], $o['description'], $now]);
        return (int) $pdo->lastInsertId();
    }

    /** Same email + description within the last 2 minutes => a double submit, not a new order. */
    public function hasRecentDuplicate(string $email, string $description): bool
    {
        $s = Database::pdo()->prepare('SELECT COUNT(*) FROM orders WHERE email = ? AND description = ? AND created_at > ?');
        $s->execute([$email, $description, gmdate('c', time() - 120)]);
        return (int) $s->fetchColumn() > 0;
    }

    public function find(int $id): ?array
    {
        $s = Database::pdo()->prepare('SELECT * FROM orders WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function latest(int $limit = 50, int $offset = 0): array
    {
        $s = Database::pdo()->prepare('SELECT * FROM orders ORDER BY id DESC LIMIT ? OFFSET ?');
        $s->execute([$limit, $offset]);
        return $s->fetchAll();
    }

    public function markNotification(int $id, string $status, string $error = ''): void
    {
        Database::pdo()->prepare('UPDATE orders SET notification_status = ?, notification_error = ?, notified_at = ? WHERE id = ?')
            ->execute([$status, mb_substr($error, 0, 300), $status === 'sent' ? gmdate('c') : null, $id]);
    }
}
