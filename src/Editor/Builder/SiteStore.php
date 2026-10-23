<?php
declare(strict_types=1);

namespace Pixelite\Editor\Builder;

use Pixelite\Editor\DraftStore;
use Pixelite\Logger;

/**
 * EXPERIMENTAL site builder – the customer's pages. Stored as storage/projects/<uid>/editor/builder/site.json, a sibling of site/ (never served by the
 * client-preview host). Only sanitised content is ever written (see HtmlSanitizer/CssSanitizer). One version number for the whole site: it is the
 * optimistic lock for every change and the browser must send the version it last saw.
 *
 * site.json: {"version":int,"updated_at":iso,"order":["index",…],"pages":{"index":{"title":"…","html":"…","css":"…"}}}
 */
final class SiteStore
{
    public const MAX_PAGES = 10;
    public const SLUG = '/^[a-z0-9][a-z0-9-]{0,39}\z/';

    public static function dir(string $uid): string
    {
        return DraftStore::dir($uid) . '/builder';
    }

    /** @return array{version:int,updated_at:string,order:list<string>,pages:array<string,array{title:string,html:string,css:string}>} */
    public static function load(string $uid): array
    {
        $file = self::dir($uid) . '/site.json';
        $d = is_file($file) && !is_link($file) ? json_decode((string) @file_get_contents($file), true) : null;
        $pages = [];
        foreach (is_array($d['pages'] ?? null) ? $d['pages'] : [] as $slug => $p) {
            if (is_string($slug) && preg_match(self::SLUG, $slug) && is_array($p) && count($pages) < self::MAX_PAGES) {
                $pages[$slug] = ['title' => mb_substr((string) ($p['title'] ?? ''), 0, 80), 'html' => (string) ($p['html'] ?? ''), 'css' => (string) ($p['css'] ?? '')];
            }
        }
        if (!isset($pages['index'])) {
            $pages = ['index' => ['title' => 'Home', 'html' => '', 'css' => '']] + $pages;
        }
        $order = array_values(array_filter(is_array($d['order'] ?? null) ? $d['order'] : [], static fn($s) => is_string($s) && isset($pages[$s])));
        $order = array_values(array_unique(array_merge($order, array_keys($pages))));
        return ['version' => max(0, (int) ($d['version'] ?? 0)), 'updated_at' => (string) ($d['updated_at'] ?? ''), 'order' => $order, 'pages' => $pages];
    }

    /**
     * Apply $change to the loaded site under an exclusive lock and write the result atomically.
     * @param callable(array):(array|string|null) $change receives the site, returns the changed site, null to refuse, or a string = refuse with that message
     * @return array{ok:bool,conflict?:bool,refused?:bool,reason?:string,version?:int,updated_at?:string}
     */
    public static function mutate(string $uid, int $baseVersion, callable $change): array
    {
        $dir = self::dir($uid);
        if (is_link($dir) || (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir))) {
            return ['ok' => false];
        }
        $lock = @fopen($dir . '/.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            return ['ok' => false];
        }
        try {
            $cur = self::load($uid);
            if ($baseVersion !== $cur['version']) {
                return ['ok' => false, 'conflict' => true, 'version' => $cur['version']];
            }
            $new = $change($cur);
            if ($new === null || is_string($new)) {
                return ['ok' => false, 'refused' => true] + (is_string($new) ? ['reason' => $new] : []);
            }
            $now = gmdate('c');
            $doc = ['version' => $cur['version'] + 1, 'updated_at' => $now, 'order' => $new['order'], 'pages' => $new['pages']];
            $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $tmp = $dir . '/.site-' . bin2hex(random_bytes(6));
            if (file_put_contents($tmp, $json) !== strlen($json)) {
                @unlink($tmp);
                return ['ok' => false];
            }
            @chmod($tmp, 0644);
            if (!@rename($tmp, $dir . '/site.json')) {
                @unlink($tmp);
                return ['ok' => false];
            }
            return ['ok' => true, 'version' => $doc['version'], 'updated_at' => $now];
        } catch (\Throwable $e) {
            Logger::error('builder site save failed', ['error' => get_class($e)]);
            return ['ok' => false];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Is an uploaded image (id m<16 hex>) used by any page or by the Vantage draft? Used to refuse deleting images that are in use. */
    public static function usesMedia(string $uid, string $id): bool
    {
        foreach (self::load($uid)['pages'] as $p) {
            if (str_contains($p['html'], '/media/' . $id) || str_contains($p['css'], '/media/' . $id)) {
                return true;
            }
        }
        return in_array($id, [(string) (DraftStore::load($uid)['values']['demo_image'] ?? '')], true);
    }
}
