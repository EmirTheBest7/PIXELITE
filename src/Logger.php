<?php
declare(strict_types=1);

namespace Pixelite;

/** Appends to storage/logs/app.log and stderr (visible via `docker compose logs`). Never pass secrets. */
final class Logger
{
    public static function write(string $level, string $message, array $context = []): void
    {
        $line = sprintf('%s %s %s %s', date('c'), strtoupper($level), $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
        $line = rtrim(str_replace(["\r", "\n"], ' ', $line));
        $dir = Paths::storage('logs');
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            @file_put_contents($dir . '/app.log', $line . "\n", FILE_APPEND | LOCK_EX);
        }
        error_log($line);
    }

    public static function info(string $m, array $c = []): void { self::write('info', $m, $c); }
    public static function warning(string $m, array $c = []): void { self::write('warning', $m, $c); }
    public static function error(string $m, array $c = []): void { self::write('error', $m, $c); }
}
