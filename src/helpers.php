<?php
declare(strict_types=1);

use Pixelite\Env;
use Pixelite\I18n;
use Pixelite\Paths;

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translate; array results (lists) are returned as arrays. */
function t(string $key, array $repl = []): mixed
{
    return I18n::get($key, $repl);
}

/** Localized URL: url('order') => /en/order, url('') => /en/ */
function url(string $path = '', ?string $locale = null): string
{
    $path = trim($path, '/');
    return '/' . ($locale ?? I18n::locale()) . '/' . $path;
}

function absolute_url(string $path): string
{
    return rtrim(Env::get('APP_URL', 'https://pixelite.cz'), '/') . $path;
}

/** Cache-busted asset URL. */
function asset(string $path): string
{
    $file = Paths::root('public/assets/' . $path);
    return '/assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function contact(): array
{
    return [
        'email' => Env::get('CONTACT_EMAIL'),
        'phone' => Env::get('CONTACT_PHONE'),
        'location' => Env::get('CONTACT_LOCATION'),
    ];
}
