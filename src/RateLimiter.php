<?php
declare(strict_types=1);

namespace Pixelite;

/** Sliding-window limiter backed by SQLite. Stores only a salted hash of the client IP. */
final class RateLimiter
{
    public static function clientKey(Request $r): string
    {
        $saltFile = Paths::storage('.salt');
        if (!is_file($saltFile)) {
            @mkdir(dirname($saltFile), 0775, true);
            @file_put_contents($saltFile, bin2hex(random_bytes(32)));
            @chmod($saltFile, 0600);
        }
        return hash_hmac('sha256', $r->ip(), (string) @file_get_contents($saltFile));
    }

    public static function blocked(string $bucket, int $limit, int $windowSeconds): bool
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM rate_limits WHERE hit_at < ?')->execute([time() - 86400]);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND hit_at > ?');
        $stmt->execute([$bucket, time() - $windowSeconds]);
        return (int) $stmt->fetchColumn() >= $limit;
    }

    /** Atomically check-and-consume one slot (no check-then-record race). False = over the limit. */
    public static function attempt(string $bucket, int $limit, int $windowSeconds): bool
    {
        $pdo = Database::pdo();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND hit_at > ?');
            $stmt->execute([$bucket, time() - $windowSeconds]);
            $ok = (int) $stmt->fetchColumn() < $limit;
            if ($ok) {
                $pdo->prepare('INSERT INTO rate_limits (bucket, hit_at) VALUES (?, ?)')->execute([$bucket, time()]);
            }
            $pdo->exec('COMMIT');
            return $ok;
        } catch (\Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    public static function clear(string $bucket): void
    {
        Database::pdo()->prepare('DELETE FROM rate_limits WHERE bucket = ?')->execute([$bucket]);
    }

    public static function record(string $bucket): void
    {
        Database::pdo()->prepare('INSERT INTO rate_limits (bucket, hit_at) VALUES (?, ?)')->execute([$bucket, time()]);
    }
}
