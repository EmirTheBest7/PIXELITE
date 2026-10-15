<?php
declare(strict_types=1);

namespace Pixelite;

final class Paths
{
    public static function root(string $sub = ''): string
    {
        return dirname(__DIR__) . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    /**
     * Web document root. Development and Docker use `public/`; the Endora shared-hosting artifact
     * ships the same files as `public_html/` (the host's fixed document-root name) next to src/, templates/, ….
     */
    public static function publicDir(string $sub = ''): string
    {
        foreach (['public', 'public_html'] as $dir) {
            if (is_dir(self::root($dir))) {
                return self::root($dir . ($sub !== '' ? '/' . ltrim($sub, '/') : ''));
            }
        }
        return self::root('public' . ($sub !== '' ? '/' . ltrim($sub, '/') : ''));
    }

    public static function storage(string $sub = ''): string
    {
        return self::root('storage' . ($sub !== '' ? '/' . ltrim($sub, '/') : ''));
    }
}
