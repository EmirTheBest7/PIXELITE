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
            package TEXT NOT NULL DEFAULT "",
            package_name TEXT NOT NULL DEFAULT "",
            package_price TEXT NOT NULL DEFAULT "",
            price_vat_mode TEXT NOT NULL DEFAULT "",
            template TEXT NOT NULL DEFAULT "",
            template_name TEXT NOT NULL DEFAULT "",
            budget TEXT NOT NULL,
            timeframe TEXT NOT NULL,
            description TEXT NOT NULL,
            consent_at TEXT NOT NULL,
            notification_status TEXT NOT NULL DEFAULT "pending",
            notification_error TEXT NOT NULL DEFAULT "",
            notified_at TEXT
        )');
        // Databases created before the package field existed: add the column in place (existing rows get "").
        $cols = array_column($pdo->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
        foreach (['package', 'package_name', 'package_price', 'price_vat_mode', 'template', 'template_name'] as $col) {   // package = canonical id; the rest = pricing snapshot
            if (!in_array($col, $cols, true)) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN ' . $col . ' TEXT NOT NULL DEFAULT ""');
            }
        }
        // Admin-only prompt library. Lives in the (git-ignored, non-web) SQLite file – never in tracked source or public assets.
        $pdo->exec('CREATE TABLE IF NOT EXISTS template_prompts (
            slug TEXT PRIMARY KEY,
            version INTEGER NOT NULL DEFAULT 1,
            body TEXT NOT NULL,
            origin TEXT NOT NULL DEFAULT "",
            notes TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL
        )');
        // ---- customer accounts (basic CRM) ----
        $pdo->exec('CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            name TEXT NOT NULL,
            company TEXT NOT NULL DEFAULT "",
            phone TEXT NOT NULL DEFAULT "",
            locale TEXT NOT NULL DEFAULT "en",
            status TEXT NOT NULL DEFAULT "active",
            created_at TEXT NOT NULL,
            consent_at TEXT NOT NULL,
            last_login_at TEXT
        )');
        // An order belongs to a customer only when the customer was signed in while ordering, or when an administrator links it on purpose. Never by matching e-mail addresses.
        if (!in_array('customer_id', $cols, true)) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN customer_id INTEGER');
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders (customer_id)');
        // ---- careers: vacancies maintained in the admin; titles/descriptions are JSON objects keyed by locale ----
        $pdo->exec('CREATE TABLE IF NOT EXISTS careers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT NOT NULL DEFAULT "draft",
            titles TEXT NOT NULL,
            descriptions TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            published_at TEXT
        )');
        // ---- client projects with a shareable browser preview. Files live in storage/projects/<uid>/site/ (never in the web root). ----
        $pdo->exec('CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "draft",
            access_enabled INTEGER NOT NULL DEFAULT 0,
            token TEXT NOT NULL UNIQUE,
            token_rotated_at TEXT NOT NULL,
            notes TEXT NOT NULL DEFAULT "",
            order_id INTEGER,
            customer_id INTEGER,
            entry TEXT NOT NULL DEFAULT "index.html",
            file_count INTEGER NOT NULL DEFAULT 0,
            total_bytes INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_customer ON projects (customer_id)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
            bucket TEXT NOT NULL,
            hit_at INTEGER NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_bucket ON rate_limits (bucket, hit_at)');
    }
}
