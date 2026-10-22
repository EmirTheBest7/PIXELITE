<?php
declare(strict_types=1);

namespace Pixelite;

/** Vacancies shown on /careers. Only rows with status "published" are ever public. Titles and descriptions are per-locale JSON; English is required. */
final class Careers
{
    public const MAX_TITLE = 120;
    public const MAX_DESCRIPTION = 8000;

    /** @param array<string,mixed> $post raw POST (titles[<loc>], descriptions[<loc>]) @return array{0: array{titles: array<string,string>, descriptions: array<string,string>}, 1: list<string>} [clean, errors] */
    public static function validate(array $post): array
    {
        $titles = $descs = $errors = [];
        foreach (I18n::LOCALES as $loc) {
            $t = is_array($post['titles'] ?? null) && is_string($post['titles'][$loc] ?? null) ? trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $post['titles'][$loc]) ?? '') : '';
            $d = is_array($post['descriptions'] ?? null) && is_string($post['descriptions'][$loc] ?? null) ? trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $post['descriptions'][$loc])) ?? '') : '';
            if (mb_strlen($t) > self::MAX_TITLE) {
                $errors[] = strtoupper($loc) . ': the title is longer than ' . self::MAX_TITLE . ' characters.';
            }
            if (mb_strlen($d) > self::MAX_DESCRIPTION) {
                $errors[] = strtoupper($loc) . ': the description is longer than ' . self::MAX_DESCRIPTION . ' characters.';
            }
            if (($t === '') !== ($d === '') && $loc !== 'en') {
                $errors[] = strtoupper($loc) . ': fill in both the title and the description, or leave both empty.';
            }
            if ($t !== '') { $titles[$loc] = $t; }
            if ($d !== '') { $descs[$loc] = $d; }
        }
        if (!isset($titles['en']) || !isset($descs['en'])) {
            $errors[] = 'The English title and description are required (they are the fallback for every language).';
        }
        return [['titles' => $titles, 'descriptions' => $descs], $errors];
    }

    /** @return list<array<string,mixed>> decoded rows; $onlyPublished=true is what public pages must use */
    public static function all(bool $onlyPublished = false): array
    {
        $sql = 'SELECT * FROM careers' . ($onlyPublished ? ' WHERE status = "published"' : '') . ' ORDER BY ' . ($onlyPublished ? 'published_at DESC, id DESC' : 'id DESC');
        return array_map([self::class, 'decode'], Database::pdo()->query($sql)->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $s = Database::pdo()->prepare('SELECT * FROM careers WHERE id = ?');
        $s->execute([$id]);
        $r = $s->fetch();
        return $r ? self::decode($r) : null;
    }

    /** @param array{titles: array<string,string>, descriptions: array<string,string>} $clean */
    public static function create(array $clean): int
    {
        $now = gmdate('c');
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO careers (status, titles, descriptions, created_at, updated_at) VALUES ("draft", ?, ?, ?, ?)')
            ->execute([json_encode($clean['titles'], JSON_UNESCAPED_UNICODE), json_encode($clean['descriptions'], JSON_UNESCAPED_UNICODE), $now, $now]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $clean): void
    {
        Database::pdo()->prepare('UPDATE careers SET titles = ?, descriptions = ?, updated_at = ? WHERE id = ?')
            ->execute([json_encode($clean['titles'], JSON_UNESCAPED_UNICODE), json_encode($clean['descriptions'], JSON_UNESCAPED_UNICODE), gmdate('c'), $id]);
    }

    public static function setPublished(int $id, bool $published): void
    {
        $now = gmdate('c');
        if ($published) {
            Database::pdo()->prepare('UPDATE careers SET status = "published", published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ?')->execute([$now, $now, $id]);
        } else {
            Database::pdo()->prepare('UPDATE careers SET status = "draft", updated_at = ? WHERE id = ?')->execute([$now, $id]);
        }
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM careers WHERE id = ?')->execute([$id]);
    }

    /** @return array{title: string, description: string} text for a locale, falling back to English */
    public static function text(array $row, ?string $locale = null): array
    {
        $locale ??= I18n::locale();
        return [
            'title' => (string) ($row['titles'][$locale] ?? $row['titles']['en'] ?? ''),
            'description' => (string) ($row['descriptions'][$locale] ?? $row['descriptions']['en'] ?? ''),
        ];
    }

    private static function decode(array $r): array
    {
        $r['titles'] = json_decode((string) $r['titles'], true) ?: [];
        $r['descriptions'] = json_decode((string) $r['descriptions'], true) ?: [];
        return $r;
    }
}
