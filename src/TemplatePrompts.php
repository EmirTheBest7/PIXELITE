<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Admin-only prompt library (one row per catalog slug). Bodies are stored as plain text and are only ever rendered
 * through e() on authenticated admin pages. They never touch config/, lang/, public assets or any public response.
 */
final class TemplatePrompts
{
    public const MAX_BODY = 20000;
    public const MAX_NOTES = 2000;
    public const MAX_IMPORT_BYTES = 1048576;

    /** @return array<string,array> slug => row (without the body) */
    public static function summaries(): array
    {
        $out = [];
        foreach (Database::pdo()->query('SELECT slug, version, origin, notes, updated_at, LENGTH(body) AS chars FROM template_prompts') as $r) {
            $out[$r['slug']] = $r;
        }
        return $out;
    }

    public static function find(string $slug): ?array
    {
        $s = Database::pdo()->prepare('SELECT * FROM template_prompts WHERE slug = ?');
        $s->execute([$slug]);
        return $s->fetch() ?: null;
    }

    /** Insert or replace; the version is bumped when the text changes. Slug must exist in the catalog. */
    public static function save(string $slug, string $body, string $origin, string $notes, ?int $version = null): int
    {
        if (!TemplateCatalog::has($slug)) {
            throw new \InvalidArgumentException('unknown slug');
        }
        $cur = self::find($slug);
        $v = $version ?? ($cur === null ? 1 : $cur['version'] + ($cur['body'] === $body ? 0 : 1));
        Database::pdo()->prepare('INSERT INTO template_prompts (slug, version, body, origin, notes, updated_at) VALUES (?,?,?,?,?,?)
            ON CONFLICT(slug) DO UPDATE SET version = excluded.version, body = excluded.body, origin = excluded.origin, notes = excluded.notes, updated_at = excluded.updated_at')
            ->execute([$slug, $v, $body, mb_substr($origin, 0, 200), mb_substr($notes, 0, self::MAX_NOTES), gmdate('c')]);
        return $v;
    }

    /**
     * Validates an uploaded JSON package: {"prompts":[{"slug","body","origin"?,"notes"?,"version"?}, …]}.
     * Nothing is written unless every entry is valid. Text is stored verbatim (never executed or rendered unescaped).
     *
     * @return array{0:int,1:list<string>} [imported count, error messages]
     */
    public static function import(string $json): array
    {
        if (strlen($json) > self::MAX_IMPORT_BYTES) {
            return [0, ['The file is larger than 1 MB.']];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['prompts']) || !is_array($data['prompts']) || !array_is_list($data['prompts']) || $data['prompts'] === []) {
            return [0, ['Not a valid prompt package: expected {"prompts":[…]}.']];
        }
        $errors = [];
        $rows = [];
        foreach ($data['prompts'] as $i => $p) {
            $n = $i + 1;
            if (!is_array($p) || !is_string($p['slug'] ?? null) || !is_string($p['body'] ?? null)) {
                $errors[] = "Entry $n: slug and body must be strings.";
                continue;
            }
            if (!TemplateCatalog::has($p['slug'])) {
                $errors[] = "Entry $n: unknown template slug.";
                continue;
            }
            $body = trim(str_replace("\r\n", "\n", $p['body']));
            if ($body === '' || mb_strlen($body) > self::MAX_BODY || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $body)) {
                $errors[] = "Entry $n: body must be 1–" . self::MAX_BODY . ' characters of text without control characters.';
                continue;
            }
            $v = $p['version'] ?? null;
            if ($v !== null && (!is_int($v) || $v < 1 || $v > 100000)) {
                $errors[] = "Entry $n: version must be a positive integer.";
                continue;
            }
            $rows[] = [$p['slug'], $body, is_string($p['origin'] ?? null) ? $p['origin'] : '', is_string($p['notes'] ?? null) ? $p['notes'] : '', $v];
        }
        if ($errors) {
            return [0, $errors];
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($rows as [$slug, $body, $origin, $notes, $v]) {
                self::save($slug, $body, $origin, $notes, $v);
            }
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        return [count($rows), []];
    }
}
