<?php
declare(strict_types=1);

namespace Pixelite\Editor\Builder\Anon;

use Pixelite\Editor\Builder\HtmlSanitizer;
use Pixelite\Editor\Builder\SiteStore;
use Pixelite\Editor\Builder\Workspace;
use Pixelite\Env;
use Pixelite\Paths;
use Pixelite\RateLimiter;
use Pixelite\Request;

/**
 * Public site builder – TEMPORARY anonymous workspaces (no account, no login, nothing published).
 *
 * Ownership  A workspace belongs to whoever holds its secret. Starting one (an explicit POST) creates a random key + secret on the server; the browser gets ONE
 *            httpOnly cookie "pixelite_bw" = "<key>.<secret>" (24 h, SameSite=Lax, Secure + __Host- over HTTPS). Only sha256(secret) is stored. No request parameter,
 *            URL or header ever names a workspace, so there is nothing to guess or to enumerate; a wrong/missing/expired cookie is simply "no workspace".
 * Storage    storage/builder/<key>/… (outside the web root, never served): meta.json, .activity (mtime = last use), editor/builder/site.json, editor/media/*.
 *            Customer projects live in storage/projects/ and are never touched by anything in here.
 * Lifetime   24 h since the last activity (TTL). Expired workspaces are deleted by cleanup(), which runs opportunistically (hosting without cron) and from
 *            bin/cleanup-builder.php. A save keeps the workspace alive; it publishes nothing.
 * Limits     per-IP creation rate, global caps on the number of workspaces and total bytes, per-workspace caps on pages, content size and images (see limits()).
 * CSRF       stateless: HMAC(secret, key) – derived from the cookie's secret, so a cross-site page can neither read nor forge it; mutations also need X-Requested-With.
 */
final class AnonWorkspaces
{
    public const TTL = 86400;                       // seconds since the last activity
    public const MAX_WORKSPACES = 250;
    public const MAX_TOTAL_BYTES = 150 * 1048576;   // all temporary workspaces together
    /** Caps can be tightened/loosened per host with BUILDER_MAX_WORKSPACES / BUILDER_MAX_BYTES (also used by the tests). */
    public static function maxWorkspaces(): int
    {
        return max(1, Env::int('BUILDER_MAX_WORKSPACES', self::MAX_WORKSPACES));
    }

    public static function maxBytes(): int
    {
        return max(1, Env::int('BUILDER_MAX_BYTES', self::MAX_TOTAL_BYTES));
    }

    private const KEY = '/^w[a-f0-9]{32}\z/';
    private const COOKIE = '/^(w[a-f0-9]{32})\.([a-f0-9]{32})\z/';

    /** @return array{pages:int,content:int,media_files:int,media_bytes:int} */
    public static function limits(): array
    {
        return ['pages' => 5, 'content' => 400_000, 'media_files' => 6, 'media_bytes' => 6 * 1048576];
    }

    public static function enabled(): bool
    {
        return Env::bool('BUILDER_PUBLIC', true);
    }

    /** Whether to advertise the builder (nav link, sitemap): switched on and PHP DOM present. Writability is only checked when someone opens the landing page. */
    public static function offered(): bool
    {
        return self::enabled() && HtmlSanitizer::available();
    }

    /** Everything the public builder needs on this host: the feature switch, PHP DOM (sanitiser) and a writable storage folder. */
    public static function available(): bool
    {
        return self::enabled() && HtmlSanitizer::available() && self::ensureRoot();
    }

    public static function root(): string
    {
        $d = Env::get('BUILDER_DIR');
        return $d !== '' ? rtrim($d, '/') : Paths::storage('builder');
    }

    private static function ensureRoot(): bool
    {
        $r = self::root();
        return (is_dir($r) || @mkdir($r, 0775, true) || is_dir($r)) && is_writable($r) && !is_link($r);
    }

    public static function isKey(string $k): bool
    {
        return preg_match(self::KEY, $k) === 1;
    }

    /** @throws \InvalidArgumentException unless $key is a workspace key */
    public static function dir(string $key): string
    {
        if (!self::isKey($key)) {
            throw new \InvalidArgumentException('bad workspace key');
        }
        return self::root() . '/' . $key;
    }

    public static function cookieName(Request $r): string
    {
        return $r->isHttps() ? '__Host-pixelite_bw' : 'pixelite_bw';
    }

    public static function workspace(string $key): Workspace
    {
        return new Workspace($key, '/builder/w', '/builder/w/media', 'a:' . $key, 'Temporary workspace', true, self::limits());
    }

    public static function csrf(string $key, string $secret): string
    {
        return hash_hmac('sha256', 'pxb-csrf|' . $key, $secret);
    }

    /** @return array{ws:Workspace,key:string,secret:string,csrf:string}|null the visitor's live workspace (and counts this request as activity) */
    public static function open(Request $r): ?array
    {
        $raw = $_COOKIE[self::cookieName($r)] ?? '';
        if (!is_string($raw) || !preg_match(self::COOKIE, $raw, $m)) {
            return null;
        }
        [, $key, $secret] = $m;
        $dir = self::root() . '/' . $key;
        if (!is_dir($dir) || is_link($dir)) {
            return null;
        }
        $meta = json_decode((string) @file_get_contents($dir . '/meta.json'), true);
        if (!is_array($meta) || !is_string($meta['h'] ?? null) || !hash_equals($meta['h'], hash('sha256', $secret))) {
            return null;
        }
        $last = (int) @filemtime($dir . '/.activity');
        if ($last === 0 || time() - $last > self::TTL) {
            return null;   // expired (cleanup removes the files)
        }
        if (time() - $last > 60) {
            @touch($dir . '/.activity');
        }
        return ['ws' => self::workspace($key), 'key' => $key, 'secret' => $secret, 'csrf' => self::csrf($key, $secret)];
    }

    /** @return array{ok:true,key:string,secret:string}|array{ok:false,reason:string} */
    public static function create(Request $r): array
    {
        if (!self::available()) {
            return ['ok' => false, 'reason' => 'unavailable'];
        }
        if (!RateLimiter::attempt('bw-new:' . RateLimiter::clientKey($r), 6, 3600)) {
            return ['ok' => false, 'reason' => 'rate'];
        }
        self::maybeCleanup();
        if (count(self::all()) >= self::maxWorkspaces() || self::globalBytes() >= self::maxBytes()) {
            self::maybeCleanup(true);
            if (count(self::all()) >= self::maxWorkspaces() || self::globalBytes(true) >= self::maxBytes()) {
                return ['ok' => false, 'reason' => 'capacity'];
            }
        }
        $key = 'w' . bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(16));
        $dir = self::root() . '/' . $key;
        if (!@mkdir($dir, 0775)) {
            return ['ok' => false, 'reason' => 'storage'];
        }
        $starter = require Paths::root('config/builder-starter.php');
        $ws = self::workspace($key);
        $clean = (new HtmlSanitizer($ws->mediaPrefix))->body((string) $starter['html']);
        $meta = json_encode(['created' => gmdate('c'), 'h' => hash('sha256', $secret)], JSON_THROW_ON_ERROR);
        if ($clean === null || file_put_contents($dir . '/meta.json', $meta) !== strlen($meta) || !@touch($dir . '/.activity')) {
            self::remove($dir);
            return ['ok' => false, 'reason' => 'storage'];
        }
        $res = SiteStore::mutate($key, 0, static function (array $site) use ($starter, $clean): array {
            $site['pages']['index'] = ['title' => (string) $starter['title'], 'html' => $clean['html'], 'css' => (string) $starter['css']];
            return $site;
        });
        if (!$res['ok']) {
            self::remove($dir);
            return ['ok' => false, 'reason' => 'storage'];
        }
        return ['ok' => true, 'key' => $key, 'secret' => $secret];
    }

    /** Delete one workspace (the caller has already proven ownership, or it is expired). */
    public static function destroy(string $key): void
    {
        if (self::isKey($key)) {
            self::remove(self::root() . '/' . $key);
        }
    }

    public static function cookieHeader(Request $r, string $key, string $secret): string
    {
        return self::cookieName($r) . '=' . $key . '.' . $secret . '; Max-Age=' . self::TTL . '; Path=/; HttpOnly; SameSite=Lax' . ($r->isHttps() ? '; Secure' : '');
    }

    // ---- start-form token (the landing page has no workspace yet, hence no per-workspace CSRF) -----------------------------------------------

    private static function salt(Request $r): string
    {
        RateLimiter::clientKey($r);   // creates storage/.salt when missing
        return (string) @file_get_contents(Paths::storage('.salt'));
    }

    /** Hourly token, valid for the current and the previous hour: a foreign site cannot read the landing page, so it cannot forge the start request. */
    public static function startToken(Request $r, int $hoursAgo = 0): string
    {
        return hash_hmac('sha256', 'pxb-start|' . (intdiv(time(), 3600) - $hoursAgo), self::salt($r));
    }

    public static function startTokenValid(Request $r, string $t): bool
    {
        return $t !== '' && (hash_equals(self::startToken($r), $t) || hash_equals(self::startToken($r, 1), $t));
    }

    // ---- lifetime ---------------------------------------------------------------------------------------------------------------------------

    /** @return list<string> workspace directories (valid keys only) */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::root() . '/w*', GLOB_ONLYDIR) ?: [] as $d) {
            if (self::isKey(basename($d)) && !is_link($d)) {
                $out[] = $d;
            }
        }
        return $out;
    }

    /**
     * Delete workspaces unused for TTL seconds. Only directories directly under the temporary-workspace root whose name is a workspace key are considered; customer
     * projects (storage/projects) are a different tree that nothing here can reach.
     * @return array{checked:int,removed:int}
     */
    public static function cleanup(?int $now = null): array
    {
        $now ??= time();
        $checked = $removed = 0;
        $root = realpath(self::root());
        if ($root === false) {
            return ['checked' => 0, 'removed' => 0];
        }
        foreach (self::all() as $d) {
            $real = realpath($d);
            if ($real === false || dirname($real) !== $root) {
                continue;
            }
            $checked++;
            $last = (int) (@filemtime($real . '/.activity') ?: @filemtime($real));
            if ($now - $last > self::TTL) {
                self::remove($real);
                $removed++;
            }
        }
        @unlink(self::root() . '/.usage');
        return ['checked' => $checked, 'removed' => $removed];
    }

    /** At most every 15 minutes (or when forced): hosting without cron still cleans up. A marker file keeps parallel requests from running it twice. */
    public static function maybeCleanup(bool $force = false): void
    {
        $m = self::root() . '/.cleanup';
        if (!$force && time() - (int) @filemtime($m) < 900) {
            return;
        }
        @touch($m);
        self::cleanup();
    }

    /** Total size of all temporary workspaces (cached for a minute). */
    public static function globalBytes(bool $fresh = false): int
    {
        $c = self::root() . '/.usage';
        $d = json_decode((string) @file_get_contents($c), true);
        if (!$fresh && is_array($d) && time() - (int) ($d['at'] ?? 0) < 60) {
            return (int) $d['bytes'];
        }
        $sum = 0;
        foreach (self::all() as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && !$f->isLink()) {
                    $sum += $f->getSize();
                }
            }
        }
        @file_put_contents($c, json_encode(['at' => time(), 'bytes' => $sum]));
        return $sum;
    }

    /** Recursive delete that never follows a symlink. */
    private static function remove(string $dir): void
    {
        if (is_link($dir) || !is_dir($dir)) {
            @unlink($dir);
            return;
        }
        foreach (scandir($dir) ?: [] as $n) {
            if ($n === '.' || $n === '..') {
                continue;
            }
            $p = $dir . '/' . $n;
            (is_dir($p) && !is_link($p)) ? self::remove($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
