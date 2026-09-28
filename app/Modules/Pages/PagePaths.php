<?php

namespace App\Modules\Pages;

use App\Core\Db;
use App\Support\Url;
use Closure;
use Throwable;

/**
 * Where a page lives: its slug under its parents' slugs, `/usluge/web-dizajn` (PLAN.md
 * D-129, resolving O-10).
 *
 * NOT STORED. The address is worked out from the page tree, once per request, by one query
 * over the pages. So a page renamed or moved changes every address below it at once, and
 * no writer can leave a stored path behind. Url::page() calls the resolver below with the
 * slug it was given, which is why none of the places that build a page's address changed.
 *
 * A slug stays unique in its language (unique (locale, slug)), so the last segment of an
 * address is enough to find a page. The path in front of it is checked against this, and a
 * request that got it wrong, after a parent was renamed or a page moved, is sent on.
 */
final class PagePaths
{
    /**
     * Counts the writes that can move an address — a slug, a parent, a page made or deleted
     * — so a resolver loaded earlier in the same request loads again: the editor saves and
     * then writes the sitemap in one request, and the sitemap must see the new address.
     */
    private static int $generation = 0;

    /** Called by every writer of a slug or a parent, and by create and delete. */
    public static function changed(): void
    {
        self::$generation++;
    }

    /**
     * A resolver for Url::usePaths(): slug in, whole path out, loaded on its first use. A
     * slug it does not know, or a path already, comes back as it went in. Before install,
     * with no pages table to read, it knows nothing and changes nothing.
     *
     * @return Closure(string, string): string
     */
    public static function resolver(Closure $db): Closure
    {
        $paths = null;
        $loaded = -1;

        return static function (string $locale, string $slug) use ($db, &$paths, &$loaded): string {
            if ($slug === '' || str_contains($slug, '/')) {
                return $slug;
            }
            if ($paths === null || $loaded !== self::$generation) {
                $loaded = self::$generation;
                try {
                    $paths = self::all($db());
                } catch (Throwable) {
                    $paths = [];
                }
            }

            return $paths[$locale][$slug] ?? $slug;
        };
    }

    /**
     * Every page's path, by language and slug. The home page, whose slug is empty, adds no
     * segment, so its children sit at the top level. A loop in the tree, which the admin
     * refuses to make, would stop at the page that closes it rather than never end.
     *
     * @return array<string, array<string, string>>
     */
    public static function all(Db $db): array
    {
        $rows = [];
        foreach ($db->all('SELECT id, locale, slug, parent_id FROM pages') as $row) {
            $rows[(int) $row['id']] = $row;
        }

        $paths = [];
        foreach ($rows as $id => $row) {
            $segments = [];
            $seen = [];
            $at = $id;
            while ($at !== null && isset($rows[$at]) && !isset($seen[$at])) {
                $seen[$at] = true;
                // A parent in another language is not a parent: the admin only offers the
                // page's own language, and a path must not change language half-way.
                if ((string) $rows[$at]['locale'] !== (string) $row['locale']) {
                    break;
                }
                if ((string) $rows[$at]['slug'] !== '') {
                    array_unshift($segments, (string) $rows[$at]['slug']);
                }
                $at = $rows[$at]['parent_id'] === null ? null : (int) $rows[$at]['parent_id'];
            }
            $paths[(string) $row['locale']][(string) $row['slug']] = implode('/', $segments);
        }

        return $paths;
    }

    /**
     * Whether every one of $segments is a slug a page of $locale has, or had: the segments of
     * an address that was real once, before a parent was renamed or a page moved.
     *
     * @param list<string> $segments
     */
    public static function known(Db $db, string $locale, array $segments): bool
    {
        $segments = array_values(array_unique($segments));
        if ($segments === []) {
            return true;
        }
        $marks = implode(', ', array_fill(0, count($segments), '?'));
        $found = [];
        foreach ($db->all("SELECT slug AS s FROM pages WHERE locale = ? AND slug IN ({$marks})", [$locale, ...$segments]) as $row) {
            $found[(string) $row['s']] = true;
        }
        foreach ($db->all("SELECT path AS s FROM redirects WHERE kind = 'history' AND locale = ? AND path IN ({$marks})", [$locale, ...$segments]) as $row) {
            $found[(string) $row['s']] = true;
        }

        return count($found) === count($segments);
    }

    /**
     * The trail a search engine is told about (BreadcrumbList): the language's home page,
     * the page's published ancestors, and the page. Empty for a page at the top level —
     * there is nothing to say that the address does not — and for the home page. An
     * unpublished ancestor is left out, since a trail may only name pages a visitor can open.
     *
     * @param array<string, mixed> $page a published page's row
     * @return list<array{name: string, url: string}>
     */
    public static function trail(Db $db, array $page): array
    {
        if ($page['parent_id'] === null || (string) $page['slug'] === '') {
            return [];
        }
        $locale = (string) $page['locale'];
        $trail = [['name' => (string) $page['title'], 'url' => Url::canonical($locale, (string) $page['slug'])]];
        $seen = [(int) $page['id'] => true];
        $at = (int) $page['parent_id'];
        while (!isset($seen[$at])) {
            $seen[$at] = true;
            $parent = $db->one('SELECT id, locale, slug, title, status, parent_id FROM pages WHERE id = ?', [$at]);
            if ($parent === null || (string) $parent['locale'] !== $locale) {
                break;
            }
            if ((string) $parent['status'] === 'published' && (string) $parent['slug'] !== '') {
                array_unshift($trail, ['name' => (string) $parent['title'], 'url' => Url::canonical($locale, (string) $parent['slug'])]);
            }
            if ($parent['parent_id'] === null) {
                break;
            }
            $at = (int) $parent['parent_id'];
        }
        $home = $db->one("SELECT title FROM pages WHERE locale = ? AND slug = '' AND status = 'published'", [$locale]);
        if ($home !== null) {
            array_unshift($trail, ['name' => (string) $home['title'], 'url' => Url::canonical($locale)]);
        }

        return count($trail) > 1 ? $trail : [];
    }

    /**
     * The trail as JSON-LD, ready for a <script type="application/ld+json">, or '' when there
     * is no trail. JSON_HEX_TAG keeps a title holding `</script>` from closing the element.
     *
     * @param list<array{name: string, url: string}> $trail
     */
    public static function jsonLd(array $trail): string
    {
        if ($trail === []) {
            return '';
        }
        $items = [];
        foreach ($trail as $position => $step) {
            $items[] = ['@type' => 'ListItem', 'position' => $position + 1, 'name' => $step['name'], 'item' => $step['url']];
        }

        return (string) json_encode(
            ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }
}
