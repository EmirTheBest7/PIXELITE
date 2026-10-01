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

/** Company identity from .env (see Pixelite\Company). */
function company(): array
{
    return \Pixelite\Company::all();
}

/** Escaped value, or a visibly marked placeholder when the owner has not configured it yet. */
function company_field(string $key): string
{
    $v = company()[$key] ?? '';
    return $v !== '' ? e($v) : '<span class="placeholder">' . e(t('company.placeholder')) . '</span>';
}

/** Consent registry (config/consent.php). */
function consent_config(): array
{
    static $cfg = null;
    return $cfg ??= require \Pixelite\Paths::root('config/consent.php');
}

/** Fills {company} {ico} {address} {email} in legal copy from .env; unset values become the localized "[to be completed]". Returns PLAIN text – escape on output. */
function legal_text(string $s): string
{
    $c = company();
    $ph = (string) t('company.placeholder');
    return strtr($s, [
        '{company}' => $c['name'] !== '' ? $c['name'] : $ph,
        '{ico}' => $c['ico'] !== '' ? $c['ico'] : $ph,
        '{address}' => $c['address'] !== '' ? $c['address'] : $ph,
        '{email}' => $c['email'] !== '' ? $c['email'] : $ph,
        '{vat}' => (string) t('vat.sentence_' . (vat_mode() ?: 'unset')),
    ]);
}

/** The Pixelite logo mark (supplied SVG, unmodified). Vector, so it stays sharp at any pixel density. Decorative: the wordmark text next to it carries the name. */
function logo_mark(string $class = ''): string
{
    return '<img' . ($class !== '' ? ' class="' . e($class) . '"' : '') . ' src="' . e(asset('img/logo.svg')) . '" alt="" width="28" height="28">';
}

/** VAT display setting from .env PRICE_VAT_MODE: incl | excl | none. Anything else (or unset) = unknown => visible placeholder, never a guess. */
function vat_mode(): string
{
    $v = strtolower(trim(\Pixelite\Env::get('PRICE_VAT_MODE')));
    return in_array($v, ['incl', 'excl', 'none'], true) ? $v : '';
}
