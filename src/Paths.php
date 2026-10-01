<?php
declare(strict_types=1);

namespace Pixelite;

final class Paths
{
    public static function root(string $sub = ''): string
    {
        return dirname(__DIR__) . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    public static function storage(string $sub = ''): string
    {
        return self::root('storage' . ($sub !== '' ? '/' . ltrim($sub, '/') : ''));
    }
}
