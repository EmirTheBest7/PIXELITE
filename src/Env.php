<?php
declare(strict_types=1);

namespace Pixelite;

/** Reads .env ourselves (no shell/compose interpolation, so `$` in password hashes is safe). */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_readable($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            self::$vars[trim($k)] = $v;
        }
    }

    public static function set(string $key, string $value): void
    {
        self::$vars[$key] = $value;
    }

    public static function get(string $key, string $default = ''): string
    {
        if (isset(self::$vars[$key]) && self::$vars[$key] !== '') {
            return self::$vars[$key];
        }
        $real = getenv($key);
        return ($real !== false && $real !== '') ? $real : $default;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return ctype_digit($v) ? (int) $v : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = strtolower(self::get($key));
        return $v === '' ? $default : in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
