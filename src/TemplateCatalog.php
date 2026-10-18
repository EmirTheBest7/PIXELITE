<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Public website-design catalog. Metadata comes from config/templates.php, copy from lang/*.php (templates.items.<slug>).
 * Contains NO prompt text – that lives in the admin-only template_prompts table.
 */
final class TemplateCatalog
{
    public const PER_PAGE = 12;
    public const SORTS = ['featured', 'az'];

    /** @var array<string,array>|null */
    private static ?array $all = null;

    /** @return array<string,array> slug => entry */
    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];
            foreach (require Paths::root('config/templates.php') as $e) {
                self::$all[$e['slug']] = $e;
            }
        }
        return self::$all;
    }

    /** Test hook: replace the catalog with fixture entries (null restores config/templates.php). Never called by the app. */
    public static function override(?array $entries): void
    {
        self::$all = $entries === null ? null : array_column($entries, null, 'slug');
    }

    public static function has(string $slug): bool
    {
        return $slug !== '' && isset(self::all()[$slug]);
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /** Fixed, server-derived English title (stored with an order so later renames never rewrite history). */
    public static function englishTitle(string $slug): string
    {
        return self::has($slug) ? (string) I18n::get("templates.items.$slug.title", [], 'en') : '';
    }

    /** Localised view of one entry. */
    public static function localized(array $e, ?string $locale = null): array
    {
        $i = I18n::get('templates.items.' . $e['slug'], [], $locale);
        return $e + ['title' => $i['title'], 'desc' => $i['desc'], 'features' => $i['features']];
    }

    /**
     * Server-side search, filters and sort. Search is case-insensitive over title, description, features,
     * category, style and tag labels in the current locale (plus the slug).
     *
     * @param array{q?:string,category?:string,style?:string,sort?:string,page?:int} $f
     * @return array{items:list<array>,total:int,page:int,pages:int,filtered:bool}
     */
    public static function search(array $f, ?string $locale = null): array
    {
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));
        $cat = (string) ($f['category'] ?? '');
        $style = (string) ($f['style'] ?? '');
        $sort = in_array($f['sort'] ?? '', self::SORTS, true) ? $f['sort'] : 'featured';
        $rows = [];
        foreach (self::all() as $e) {
            if (($cat !== '' && $e['category'] !== $cat) || ($style !== '' && $e['style'] !== $style)) {
                continue;
            }
            $l = self::localized($e, $locale);
            if ($q !== '') {
                $hay = [$l['title'], $l['desc'], implode(' ', $l['features']), $e['slug'],
                    (string) I18n::get('templates.categories.' . $e['category'] . '.name', [], $locale),
                    (string) I18n::get('templates.styles.' . $e['style'], [], $locale)];
                foreach ($e['tags'] as $t) {
                    $hay[] = (string) I18n::get("templates.tags.$t", [], $locale);
                }
                $match = true;
                foreach (preg_split('/\s+/u', $q) ?: [] as $word) {   // every word must appear somewhere
                    if (!str_contains(mb_strtolower(implode(' ', $hay)), $word)) {
                        $match = false;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
            }
            $rows[] = $l;
        }
        $collator = class_exists(\Collator::class) ? new \Collator($locale === 'cs' ? 'cs_CZ' : 'en_US') : null;
        $byTitle = static fn(array $a, array $b): int => $collator ? $collator->compare($a['title'], $b['title']) : strcasecmp($a['title'], $b['title']);
        usort($rows, $sort === 'az'
            ? $byTitle
            : static fn(array $a, array $b): int => ($b['featured'] <=> $a['featured']) ?: $byTitle($a, $b));
        $total = count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($f['page'] ?? 1)), $pages);
        return [
            'items' => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total, 'page' => $page, 'pages' => $pages,
            'filtered' => $q !== '' || $cat !== '' || $style !== '' || $sort !== 'featured' || $page > 1,
        ];
    }

    /** Categories that actually have entries (the UI only offers what the data supports). @return list<string> */
    public static function categories(): array
    {
        return array_values(array_unique(array_column(self::all(), 'category')));
    }

    /** @return list<string> */
    public static function styles(): array
    {
        return array_values(array_unique(array_column(self::all(), 'style')));
    }

    /** "<from> <amount> <currency>" – straight from the Template-website package data, so there is one source of truth for the price. */
    public static function priceText(): string
    {
        foreach ((array) I18n::get('services.items') as $it) {
            if (($it['id'] ?? '') === 'template') {
                return $it['from'] . ' ' . $it['amount'] . ' ' . $it['currency'];
            }
        }
        return '';
    }

    public static function mediaUrl(array $e, string $file): string
    {
        return asset($e['media']['dir'] . '/' . $file);
    }
}
