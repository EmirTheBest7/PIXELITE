<?php
declare(strict_types=1);

namespace Pixelite\Editor;

use Pixelite\Paths;

/**
 * EXPERIMENTAL visual editor. Field schema + validation: the only gate between the browser and a draft.
 * The browser sends typed VALUES for known keys; markup is never accepted. See config/editor.php.
 */
final class Schema
{
    public const TEMPLATE = 'vantage';   // the one template of this experiment
    public const MAX_VALUES_BYTES = 16384;

    /** @return array<string,mixed> */
    public static function template(string $slug = self::TEMPLATE): array
    {
        static $all = null;
        $all ??= require Paths::root('config/editor.php');
        if (!isset($all[$slug])) {
            throw new \InvalidArgumentException('unknown editor template');
        }
        return $all[$slug];
    }

    /** @return array<string,array<string,mixed>> */
    public static function fields(string $slug = self::TEMPLATE): array
    {
        return self::template($slug)['fields'];
    }

    /** @return array<string,mixed> key => default */
    public static function defaults(string $slug = self::TEMPLATE): array
    {
        return array_map(static fn(array $f) => $f['default'], self::fields($slug));
    }

    /**
     * @param array<mixed> $in           decoded JSON object from the browser (untrusted)
     * @param callable(string):bool $mediaExists  tells whether an image id belongs to the project being saved
     * @return array{0: array<string,mixed>, 1: array<string,string>} [clean values, errors by field]
     */
    public static function validate(array $in, callable $mediaExists, string $slug = self::TEMPLATE): array
    {
        $fields = self::fields($slug);
        $clean = [];
        $errors = [];
        foreach ($in as $key => $raw) {
            if (!is_string($key) || !isset($fields[$key])) {
                $errors[is_string($key) ? mb_substr(preg_replace('/[^A-Za-z0-9_]/', '?', $key) ?? '', 0, 40) : '?'] = 'Unknown field.';
            }
        }
        foreach ($fields as $key => $f) {
            if (!array_key_exists($key, $in)) {
                continue;   // partial saves are allowed; the rest keeps its stored value
            }
            $v = $in[$key];
            $err = null;
            $clean[$key] = match ($f['type']) {
                'text' => self::text($v, (int) $f['max'], empty($f['optional']), $err),
                'multiline' => self::multiline($v, (int) $f['max'], (int) $f['lines'], $err),
                'link' => self::link($v, $err),
                'color' => self::color($v, $err),
                'bool' => self::bool($v, $err),
                'image' => self::image($v, $mediaExists, $err),
            };
            if ($err !== null) {
                $errors[$key] = $err;
            }
        }
        return [$clean, $errors];
    }

    private static function plain(mixed $v): ?string
    {
        if (!is_string($v) || !mb_check_encoding($v, 'UTF-8')) {
            return null;
        }
        // control characters, line/paragraph separators and bidi override/isolate marks (visual spoofing) are dropped
        return preg_replace('/[\x00-\x08\x0B-\x1F\x7F\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', str_replace("\r\n", "\n", $v)) ?? null;
    }

    private static function text(mixed $v, int $max, bool $required, ?string &$err): string
    {
        $s = self::plain($v);
        if ($s === null) {
            $err = 'Invalid text.';
            return '';
        }
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        if ($required && $s === '') {
            $err = 'This text cannot be empty.';
        } elseif (mb_strlen($s) > $max) {
            $err = "Use at most $max characters.";
            return mb_substr($s, 0, $max);
        }
        return $s;
    }

    private static function multiline(mixed $v, int $max, int $maxLines, ?string &$err): string
    {
        $s = self::plain($v);
        if ($s === null) {
            $err = 'Invalid text.';
            return '';
        }
        $lines = array_values(array_filter(array_map(static fn(string $l) => trim(preg_replace('/[ \t]+/u', ' ', $l) ?? ''), explode("\n", $s)), static fn(string $l) => $l !== ''));
        $s = implode("\n", $lines);
        if ($s === '') {
            $err = 'This text cannot be empty.';
        } elseif (count($lines) > $maxLines) {
            $err = "Use at most $maxLines lines.";
        } elseif (mb_strlen($s) > $max) {
            $err = "Use at most $max characters.";
        }
        return $s;
    }

    /** Whitelist, not blacklist: https/http URLs, mailto:, tel:, "#anchor". No javascript:, data:, vbscript:, protocol-relative or relative paths. */
    private static function link(mixed $v, ?string &$err): string
    {
        $s = is_string($v) ? trim($v) : '';
        if (!is_string($v) || strlen($s) > 300 || preg_match('/[\x00-\x20\x7F"\'<>\\\\`]/', $s) || !mb_check_encoding($s, 'UTF-8')) {
            $err = 'Enter a valid link.';
            return '';
        }
        if ($s === '' || preg_match('/^#[A-Za-z0-9_-]{0,64}\z/', $s)) {
            return $s === '' ? '#' : $s;
        }
        if (preg_match('#^https?://#i', $s)) {
            $p = parse_url($s);
            if (is_array($p) && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']) && preg_match('/^[A-Za-z0-9.-]+\z/', $p['host']) && str_contains($p['host'], '.')) {
                return $s;
            }
        } elseif (preg_match('/^mailto:([^?]+)\z/i', $s, $m) && filter_var($m[1], FILTER_VALIDATE_EMAIL)) {
            return $s;
        } elseif (preg_match('/^tel:\+?[0-9]{5,20}\z/', $s)) {
            return $s;
        }
        $err = 'Links may start with https://, http://, mailto:, tel: or #.';
        return '';
    }

    private static function color(mixed $v, ?string &$err): string
    {
        if (is_string($v) && ($v === '' || preg_match('/^#[0-9a-fA-F]{6}\z/', $v))) {
            return strtolower($v);
        }
        $err = 'Use a colour like #1b45ff.';
        return '';
    }

    private static function bool(mixed $v, ?string &$err): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if ($v === 1 || $v === 0 || $v === '1' || $v === '0') {
            return $v === 1 || $v === '1';
        }
        $err = 'Invalid choice.';
        return true;
    }

    private static function image(mixed $v, callable $mediaExists, ?string &$err): string
    {
        if ($v === '') {
            return '';
        }
        if (!is_string($v) || !preg_match('/^m[a-f0-9]{16}\z/', $v) || !$mediaExists($v)) {
            $err = 'Unknown image.';
            return '';
        }
        return $v;
    }

    /** Contrast-safe label colour for a custom button background (the same rule runs in the browser for the live canvas). */
    public static function onColor(string $hex): string
    {
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        $lin = static fn(int $c): float => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $l = 0.2126 * $lin((int) $r) + 0.7152 * $lin((int) $g) + 0.0722 * $lin((int) $b);
        return $l > 0.179 ? '#111111' : '#ffffff';
    }
}
