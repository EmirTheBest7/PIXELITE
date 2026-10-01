<?php
declare(strict_types=1);

namespace Pixelite;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = Env::get('LEADS_DATABASE_PATH', Paths::storage('leads.sqlite'));
            if ($path !== ':memory:' && !is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA busy_timeout = 5000');
            if ($path !== ':memory:') {
                $pdo->exec('PRAGMA journal_mode = WAL');
            }
            self::migrate($pdo);
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    /** For tests: drop the cached connection so the next call re-reads LEADS_DATABASE_PATH. */
    public static function reset(): void
    {
        self::$pdo = null;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            locale TEXT NOT NULL,
            name TEXT NOT NULL,
            company TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL,
            phone TEXT NOT NULL DEFAULT "",
            project_type TEXT NOT NULL,
            budget TEXT NOT NULL,
            timeframe TEXT NOT NULL,
            description TEXT NOT NULL,
            consent_at TEXT NOT NULL,
            notification_status TEXT NOT NULL DEFAULT "pending",
            notification_error TEXT NOT NULL DEFAULT "",
            notified_at TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
            bucket TEXT NOT NULL,
            hit_at INTEGER NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_bucket ON rate_limits (bucket, hit_at)');
    }
}
