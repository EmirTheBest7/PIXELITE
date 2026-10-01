<?php
declare(strict_types=1);

namespace Pixelite;

final class I18n
{
    public const LOCALES = ['en', 'cs'];

    private static string $locale = 'en';
    /** @var array<string,array> */
    private static array $cache = [];

    public static function default(): string
    {
        $d = Env::get('APP_LOCALE', 'en');
        return in_array($d, self::LOCALES, true) ? $d : 'en';
    }

    public static function set(string $locale): void
    {
        self::$locale = in_array($locale, self::LOCALES, true) ? $locale : self::default();
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** Dot-path lookup. Arrays are returned as-is (for lists); missing keys fall back to English, then the key. */
    public static function get(string $key, array $repl = [], ?string $locale = null): mixed
    {
        $locale ??= self::$locale;
        $val = self::lookup(self::load($locale), $key) ?? self::lookup(self::load('en'), $key);
        if ($val === null) {
            Logger::warning('missing translation', ['key' => $key, 'locale' => $locale]);
            return $key;
        }
        if (is_string($val) && $repl) {
            foreach ($repl as $k => $v) {
                $val = str_replace('{' . $k . '}', (string) $v, $val);
            }
        }
        return $val;
    }

    /** @return array<string,mixed> flattened dot keys (used by the parity test) */
    public static function flatten(array $a, string $prefix = ''): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            is_array($v) ? $out += self::flatten($v, $prefix . $k . '.') : $out[$prefix . $k] = $v;
        }
        return $out;
    }

    public static function load(string $locale): array
    {
        return self::$cache[$locale] ??= require Paths::root("lang/$locale.php");
    }

    private static function lookup(array $arr, string $key): mixed
    {
        foreach (explode('.', $key) as $part) {
            if (!is_array($arr) || !array_key_exists($part, $arr)) {
                return null;
            }
            $arr = $arr[$part];
        }
        return $arr;
    }
}
