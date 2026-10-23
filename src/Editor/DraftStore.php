<?php
declare(strict_types=1);

namespace Pixelite\Editor;

use Pixelite\Logger;
use Pixelite\ProjectStorage;

/**
 * EXPERIMENTAL visual editor. Drafts live at storage/projects/<uid>/editor/draft.json – a SIBLING of the project's site/ folder, so a draft is
 * never served by the client-preview host (which only reads site/) and is removed together with the project (ProjectStorage::remove).
 * The path is derived from the project's random internal uid only; no caller supplies a file name or directory.
 */
final class DraftStore
{
    public static function dir(string $uid): string
    {
        if (Builder\Anon\AnonWorkspaces::isKey($uid)) {
            return Builder\Anon\AnonWorkspaces::dir($uid) . '/editor';   // temporary public workspace (never a customer project)
        }
        return ProjectStorage::dir($uid) . '/editor';   // validates the 16-hex uid
    }

    /** @return array{version:int,updated_at:string,values:array<string,mixed>} the stored draft merged over the template defaults */
    public static function load(string $uid): array
    {
        $defaults = Schema::defaults();
        $file = self::dir($uid) . '/draft.json';
        $data = is_file($file) && !is_link($file) ? json_decode((string) @file_get_contents($file), true) : null;
        if (!is_array($data) || !is_array($data['values'] ?? null)) {
            return ['version' => 0, 'updated_at' => '', 'values' => $defaults];
        }
        // Stored data is re-validated on the way out: a damaged file can never inject anything into a page.
        [$clean] = Schema::validate($data['values'], static fn(string $id): bool => Media::exists($uid, $id));
        return ['version' => max(0, (int) ($data['version'] ?? 0)), 'updated_at' => (string) ($data['updated_at'] ?? ''), 'values' => $clean + $defaults];
    }

    /**
     * @param array<string,mixed> $clean already validated values (partial)
     * @return array{ok:bool,conflict?:bool,version?:int,updated_at?:string}
     */
    public static function save(string $uid, int $baseVersion, array $clean): array
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
                return ['ok' => false, 'conflict' => true, 'version' => $cur['version']];   // another tab/device saved in between
            }
            $now = gmdate('c');
            $doc = ['template' => Schema::TEMPLATE, 'version' => $cur['version'] + 1, 'updated_at' => $now, 'values' => $clean + $cur['values']];
            $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $tmp = $dir . '/.draft-' . bin2hex(random_bytes(6));
            if (file_put_contents($tmp, $json) !== strlen($json)) {
                @unlink($tmp);
                return ['ok' => false];
            }
            @chmod($tmp, 0644);
            if (!@rename($tmp, $dir . '/draft.json')) {
                @unlink($tmp);
                return ['ok' => false];
            }
            return ['ok' => true, 'version' => $doc['version'], 'updated_at' => $now];
        } catch (\Throwable $e) {
            Logger::error('editor draft save failed', ['error' => get_class($e)]);
            return ['ok' => false];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
