<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Two presentations of the SAME application: "new" (templates/, public/assets/site.css) and "legacy" (templates/legacy/, public/assets/style.css).
 * Controllers, validation, database, authentication and authorization are shared; only the templates differ.
 * The default variant is served at the normal URLs (/en/…); the other one under its own prefix (/legacy/en/…).
 * DESIGN_DEFAULT=legacy in .env swaps the roles (legacy at /en/…, the new design at /new/en/…): a one-line rollback that needs no deploy,
 * and the harness the markup-level legacy test suite runs against.
 */
final class Design
{
    public const VARIANTS = ['new', 'legacy'];

    private static ?string $current = null;

    public static function default(): string
    {
        $d = Env::get('DESIGN_DEFAULT', 'new');
        return in_array($d, self::VARIANTS, true) ? $d : 'new';
    }

    public static function alternate(): string
    {
        return self::default() === 'new' ? 'legacy' : 'new';
    }

    public static function set(string $variant): void
    {
        self::$current = in_array($variant, self::VARIANTS, true) ? $variant : self::default();
    }

    public static function current(): string
    {
        return self::$current ?? self::default();
    }

    public static function isLegacy(): bool
    {
        return self::current() === 'legacy';
    }

    /** URL prefix of a variant: '' for the default one, '/legacy' or '/new' for the other. */
    public static function prefix(?string $variant = null): string
    {
        $v = $variant ?? self::current();
        return $v === self::default() ? '' : '/' . $v;
    }

    /** Template path (relative to the project root, without .php) for the variant being rendered. */
    public static function templatePath(string $template): string
    {
        return (self::current() === 'legacy' ? 'templates/legacy/' : 'templates/') . $template . '.php';
    }
}
