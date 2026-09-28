<?php

namespace App\Modules\Pages;

use App\Core\Db;

/**
 * The page hierarchy. `parent_id` has been in the schema since Slice 3 with nothing
 * reading it; this is what reads it.
 *
 * It sits beside Page rather than inside it because Page is already close to the file
 * size limit, and because the same tree is what breadcrumbs, the page_list block and
 * nested addresses (PLAN.md §5, O-10) will each need.
 */
final class PageTree
{
    /**
     * The pages that may be the parent of $pageId, in tree order, each with the depth it
     * sits at so a select can indent them.
     *
     * A page may not be its own parent, nor be parented to one of its own descendants.
     * Either makes a cycle, and a cycle is not a display problem: every later reader of
     * the tree — breadcrumbs, menus, an address built from ancestors — would have to
     * defend against looping for ever.
     *
     * @param int|null $pageId the page being edited, or null when creating one
     * @return list<array{id: int, title: string, depth: int}>
     */
    public static function parentOptions(Db $db, string $locale, ?int $pageId): array
    {
        $children = self::children($db, $locale);
        $excluded = [];
        if ($pageId !== null) {
            $excluded = self::descendants($children, $pageId);
            $excluded[$pageId] = true;
        }

        $options = [];
        self::walk($children, 0, 0, $excluded, $options);

        return $options;
    }

    /**
     * The admin page list: every page, in tree order, with the depth it sits at and
     * whether it is the first or last of its siblings — the Up and Down buttons are
     * disabled at the ends rather than silently doing nothing.
     *
     * Locales are kept apart. A page is ordered among its siblings within one locale, so
     * interleaving two languages in one tree would show an order nothing can act on.
     *
     * Each row carries its parent, 0 at the top level: siblings are the pages sharing one
     * parent, never the pages at one depth. Keying a drag on depth would let a child be
     * dragged into another parent's run of rows, which the server then refuses — a move
     * that looks like it worked and did not.
     *
     * @return list<array{id: int, locale: string, slug: string, title: string, status: string, updated: string, parent: int, depth: int, first: bool, last: bool}>
     */
    public static function listing(Db $db): array
    {
        $byLocale = [];
        $rows = $db->all('SELECT id, locale, slug, title, status, parent_id, updated_at FROM pages ORDER BY locale, sort, title, id');
        foreach ($rows as $row) {
            $parent = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            $byLocale[(string) $row['locale']][$parent][] = [
                'id' => (int) $row['id'],
                'locale' => (string) $row['locale'],
                'slug' => (string) $row['slug'],
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'updated' => (string) $row['updated_at'],
                'parent' => $parent,
            ];
        }

        $listing = [];
        foreach ($byLocale as $children) {
            self::flatten($children, 0, 0, $listing);
        }

        return $listing;
    }

    /**
     * The position a page takes when it joins the siblings under $parentId: one past the
     * last of them, or 0 when it is the first.
     */
    public static function nextSort(Db $db, string $locale, ?int $parentId): int
    {
        $row = $parentId === null
            ? $db->one('SELECT COALESCE(MAX(sort), -1) + 1 AS next FROM pages WHERE locale = ? AND parent_id IS NULL', [$locale])
            : $db->one('SELECT COALESCE(MAX(sort), -1) + 1 AS next FROM pages WHERE locale = ? AND parent_id = ?', [$locale, $parentId]);

        return (int) ($row['next'] ?? 0);
    }

    /**
     * Writes $ids as the order of one sibling group, positions 0 upward.
     *
     * Every id must already be a sibling of the first: the order arrives from a form, so
     * it is a claim about the tree rather than a fact, and a request naming pages from
     * two different parents would otherwise re-file them by writing positions.
     *
     * @param list<int> $ids
     */
    public static function reorder(Db $db, array $ids): bool
    {
        if ($ids === []) {
            return false;
        }
        $first = $db->one('SELECT locale, parent_id FROM pages WHERE id = ?', [$ids[0]]);
        if ($first === null) {
            return false;
        }
        $siblings = self::siblingIds($db, (string) $first['locale'], self::parentOf($first));
        if (count($ids) !== count($siblings) || array_diff($ids, $siblings) !== []) {
            return false;
        }
        foreach ($ids as $position => $id) {
            $db->query('UPDATE pages SET sort = ? WHERE id = ?', [$position, $id]);
        }

        return true;
    }

    /**
     * Swaps a page with the sibling above or below it. The no-JavaScript path, and the
     * only one a keyboard reaches.
     */
    public static function move(Db $db, int $id, string $direction): bool
    {
        $page = $db->one('SELECT locale, parent_id FROM pages WHERE id = ?', [$id]);
        if ($page === null) {
            return false;
        }
        $siblings = self::siblingIds($db, (string) $page['locale'], self::parentOf($page));
        $at = array_search($id, $siblings, true);
        if ($at === false) {
            return false;
        }
        $to = $direction === 'up' ? $at - 1 : $at + 1;
        if ($to < 0 || $to >= count($siblings)) {
            return false;
        }

        // Rebuilt rather than swapped in place: the two positions are read back out of a
        // list, so the order stays a list by construction.
        $swapped = [];
        foreach ($siblings as $index => $sibling) {
            $swapped[] = match ($index) {
                $at => $siblings[$to],
                $to => $siblings[$at],
                default => $sibling,
            };
        }

        return self::reorder($db, $swapped);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function parentOf(array $row): ?int
    {
        return $row['parent_id'] === null ? null : (int) $row['parent_id'];
    }

    /**
     * One sibling group in the order the list shows it, so a swap moves a page past the
     * page it actually appears next to.
     *
     * @return list<int>
     */
    private static function siblingIds(Db $db, string $locale, ?int $parentId): array
    {
        $rows = $parentId === null
            ? $db->all('SELECT id FROM pages WHERE locale = ? AND parent_id IS NULL ORDER BY sort, title, id', [$locale])
            : $db->all('SELECT id FROM pages WHERE locale = ? AND parent_id = ? ORDER BY sort, title, id', [$locale, $parentId]);

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @param array<int, list<array{id: int, locale: string, slug: string, title: string, status: string, updated: string, parent: int}>> $children
     * @param list<array{id: int, locale: string, slug: string, title: string, status: string, updated: string, parent: int, depth: int, first: bool, last: bool}> $listing
     */
    private static function flatten(array $children, int $parent, int $depth, array &$listing): void
    {
        // The same guard parentOptions() walks under: a cycle stored by an older version
        // or by hand would recurse for ever.
        if ($depth > 20) {
            return;
        }
        $group = $children[$parent] ?? [];
        $last = count($group) - 1;
        foreach ($group as $index => $page) {
            $listing[] = $page + ['depth' => $depth, 'first' => $index === 0, 'last' => $index === $last];
            self::flatten($children, $page['id'], $depth + 1, $listing);
        }
    }

    /**
     * Every page in $locale keyed by its parent, with a missing parent keyed under 0.
     *
     * @return array<int, list<array{id: int, title: string}>>
     */
    private static function children(Db $db, string $locale): array
    {
        $children = [];
        // sort first, then title as the tie-break: every page created before ordering
        // existed sits at the default 0, so without it the tree would shuffle by id
        // (PLAN.md D-011).
        foreach ($db->all('SELECT id, parent_id, title FROM pages WHERE locale = ? ORDER BY sort, title, id', [$locale]) as $row) {
            $parent = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            $children[$parent][] = ['id' => (int) $row['id'], 'title' => (string) $row['title']];
        }

        return $children;
    }

    /**
     * @param array<int, list<array{id: int, title: string}>> $children
     * @param array<int, true> $seen guards against a cycle already stored by an older
     *                               version or by hand; without it this recurses for ever
     * @return array<int, true>
     */
    private static function descendants(array $children, int $pageId, array $seen = []): array
    {
        $found = [];
        foreach ($children[$pageId] ?? [] as $child) {
            if (isset($seen[$child['id']])) {
                continue;
            }
            $seen[$child['id']] = true;
            $found[$child['id']] = true;
            $found += self::descendants($children, $child['id'], $seen);
        }

        return $found;
    }

    /**
     * @param array<int, list<array{id: int, title: string}>> $children
     * @param array<int, true> $excluded
     * @param list<array{id: int, title: string, depth: int}> $options
     */
    private static function walk(array $children, int $parent, int $depth, array $excluded, array &$options): void
    {
        // A tree deeper than this is a cycle, whatever the rows claim.
        if ($depth > 20) {
            return;
        }
        foreach ($children[$parent] ?? [] as $child) {
            if (isset($excluded[$child['id']])) {
                continue;
            }
            $options[] = ['id' => $child['id'], 'title' => $child['title'], 'depth' => $depth];
            self::walk($children, $child['id'], $depth + 1, $excluded, $options);
        }
    }
}
