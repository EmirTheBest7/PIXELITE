<?php
declare(strict_types=1);

namespace Pixelite;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function valid(string $submitted): bool
    {
        $t = $_SESSION['_csrf'] ?? '';
        return is_string($t) && $t !== '' && hash_equals($t, $submitted);
    }
}
