<?php

namespace App\Modules\Pages;

use App\Core\Db;

/**
 * Where a page stands in its language's tree: under which parent, and where among the pages
 * there (PLAN.md D-133). The page list's → and ← and its drag all come here, and so does
 * Undo.
 *
 * THE RULES, all on the server, whatever the list offered:
 *   - a parent in the page's own language, never the page itself or one below it
 *   - never under a home page: its children stand at the top level of the address, so a
 *     page placed there would look nested in the list and not be
 *   - at most MAX_LEVELS levels, counting the pages the moved page carries with it
 *
 * TRANSLATIONS FOLLOW (the owner's A-2). Placing a page does the same in every language
 * where the new parent has a translation too, or at the top level, which every language
 * has. A language where the parent is not translated keeps its tree as it is.
 */
final class PagePlacing
{
    public const MAX_LEVELS = 3;

    /**
     * Places page $id under $parentId (null: the top level) at $position among the pages
     * there (null: last), and its translations with it. Returns null when it did, or why not.
     */
    public static function place(Db $db, int $id, ?int $parentId, ?int $position): ?string
    {
        $page = $db->one('SELECT id, locale, parent_id, content_group_id FROM pages WHERE id = ?', [$id]);
        if ($page === null) {
            return t('pages.not_found');
        }
        $problem = self::problem($db, $page, $parentId);
        if ($problem !== null) {
            return $problem;
        }

        return $db->transaction(static function () use ($db, $page, $parentId, $position): ?string {
            self::put($db, (int) $page['id'], (string) $page['locale'], $parentId, $position);

            // The same place in the other languages, wherever the parent is there too.
            $group = (int) ($page['content_group_id'] ?? $page['id']);
            $parentGroup = null;
            if ($parentId !== null) {
                $parentGroup = (int) ($db->one('SELECT content_group_id FROM pages WHERE id = ?', [$parentId])['content_group_id'] ?? $parentId);
            }
            foreach ($db->all('SELECT id, locale, parent_id, content_group_id FROM pages WHERE content_group_id = ? AND id <> ?', [$group, (int) $page['id']]) as $translation) {
                $there = null;
                if ($parentGroup !== null) {
                    $row = $db->one('SELECT id FROM pages WHERE content_group_id = ? AND locale = ?', [$parentGroup, (string) $translation['locale']]);
                    if ($row === null) {
                        continue;
                    }
                    $there = (int) $row['id'];
                }
                if (self::problem($db, $translation, $there) === null) {
                    self::put($db, (int) $translation['id'], (string) $translation['locale'], $there, self::sourcePosition($db, (int) $translation['id'], $there));
                }
            }
            PagePaths::changed();

            return null;
        });
    }

    /**
     * → : under the sibling just above it, as that page's last child.
     */
    public static function indent(Db $db, int $id): ?string
    {
        $page = $db->one('SELECT locale, parent_id FROM pages WHERE id = ?', [$id]);
        if ($page === null) {
            return t('pages.not_found');
        }
        $siblings = self::siblings($db, (string) $page['locale'], $page['parent_id'] === null ? null : (int) $page['parent_id']);
        $at = array_search($id, $siblings, true);
        if ($at === false || $at === 0) {
            return t('pages.place.nothing_above');
        }

        return self::place($db, $id, $siblings[$at - 1], null);
    }

    /**
     * ← : out one level, just after the page it was under.
     */
    public static function outdent(Db $db, int $id): ?string
    {
        $page = $db->one('SELECT locale, parent_id FROM pages WHERE id = ?', [$id]);
        if ($page === null || $page['parent_id'] === null) {
            return t('pages.place.top_already');
        }
        $parent = $db->one('SELECT id, parent_id FROM pages WHERE id = ?', [(int) $page['parent_id']]);
        if ($parent === null) {
            return t('pages.not_found');
        }
        $grandparent = $parent['parent_id'] === null ? null : (int) $parent['parent_id'];
        $after = array_search((int) $parent['id'], self::siblings($db, (string) $page['locale'], $grandparent), true);

        return self::place($db, $id, $grandparent, $after === false ? null : $after + 1);
    }

    /**
     * A translation just made (Translations::create) takes the tree its source has, as far
     * as this language has it (the owner's A-2):
     *   - its place among its siblings follows the source's order, not the moment it was
     *     translated
     *   - it adopts this language's translations of its source's children that stood at the
     *     top level, because the parent was not translated when they were
     */
    public static function translated(Db $db, int $translationId): void
    {
        $page = $db->one('SELECT id, locale, parent_id, content_group_id FROM pages WHERE id = ?', [$translationId]);
        if ($page === null) {
            return;
        }
        $parentId = $page['parent_id'] === null ? null : (int) $page['parent_id'];
        self::put($db, $translationId, (string) $page['locale'], $parentId, self::sourcePosition($db, $translationId, $parentId));

        $source = $db->one("SELECT id FROM pages WHERE content_group_id = ? AND translation_status = 'source'", [(int) $page['content_group_id']]);
        if ($source === null || (int) $source['id'] === $translationId) {
            return;
        }
        $orphans = $db->all(
            'SELECT t.id, t.locale, t.parent_id, t.content_group_id FROM pages c JOIN pages t ON t.content_group_id = c.content_group_id
             WHERE c.parent_id = ? AND t.locale = ? AND t.parent_id IS NULL',
            [(int) $source['id'], (string) $page['locale']],
        );
        foreach ($orphans as $orphan) {
            if (self::problem($db, $orphan, $translationId) === null) {
                self::put($db, (int) $orphan['id'], (string) $orphan['locale'], $translationId, self::sourcePosition($db, (int) $orphan['id'], $translationId));
            }
        }
        PagePaths::changed();
    }

    /**
     * Where page $id stands now, for Undo to put it back.
     *
     * @return array{parent: int|null, position: int}|null
     */
    public static function whereIs(Db $db, int $id): ?array
    {
        $page = $db->one('SELECT locale, parent_id FROM pages WHERE id = ?', [$id]);
        if ($page === null) {
            return null;
        }
        $parent = $page['parent_id'] === null ? null : (int) $page['parent_id'];
        $at = array_search($id, self::siblings($db, (string) $page['locale'], $parent), true);

        return ['parent' => $parent, 'position' => $at === false ? 0 : $at];
    }

    /**
     * A translation's place among its new siblings that matches its source's order: before
     * the first sibling whose source comes after its own. Null (last) when nothing says.
     */
    public static function sourcePosition(Db $db, int $id, ?int $parentId): ?int
    {
        $key = self::sourceSort($db, $id);
        if ($key === null) {
            return null;
        }
        $page = $db->one('SELECT locale FROM pages WHERE id = ?', [$id]);
        $position = 0;
        foreach (self::siblings($db, (string) ($page['locale'] ?? ''), $parentId) as $sibling) {
            if ($sibling === $id) {
                continue;
            }
            $theirs = self::sourceSort($db, $sibling);
            if ($theirs !== null && $theirs > $key) {
                return $position;
            }
            $position++;
        }

        return null;
    }

    /**
     * Why $page may not stand under $parentId, or null when it may.
     *
     * @param array<string, mixed> $page
     */
    private static function problem(Db $db, array $page, ?int $parentId): ?string
    {
        $id = (int) $page['id'];
        $depth = 1;
        if ($parentId !== null) {
            $parent = $db->one('SELECT id, locale, slug FROM pages WHERE id = ?', [$parentId]);
            if ($parent === null || (string) $parent['locale'] !== (string) $page['locale']) {
                return t('pages.place.other_language');
            }
            if ((string) $parent['slug'] === '') {
                return t('pages.place.home');
            }
            // The page itself, or one below it: walk up from the parent.
            $seen = [];
            for ($at = $parentId; $at !== null && !isset($seen[$at]); ) {
                if ($at === $id) {
                    return t('pages.place.own_child');
                }
                $seen[$at] = true;
                $depth++;
                $up = $db->one('SELECT parent_id FROM pages WHERE id = ?', [$at]);
                $at = $up === null || $up['parent_id'] === null ? null : (int) $up['parent_id'];
            }
        }
        if ($depth + self::height($db, $id) - 1 > self::MAX_LEVELS) {
            return t('pages.place.too_deep', ['levels' => (string) self::MAX_LEVELS]);
        }

        return null;
    }

    /** How many levels a page and everything under it take: 1 for a page with no children. */
    private static function height(Db $db, int $id, int $guard = 0): int
    {
        if ($guard > 20) {
            return 1;
        }
        $tallest = 0;
        foreach ($db->all('SELECT id FROM pages WHERE parent_id = ?', [$id]) as $child) {
            $tallest = max($tallest, self::height($db, (int) $child['id'], $guard + 1));
        }

        return 1 + $tallest;
    }

    /** Moves the row and writes the new order of the pages it now stands among. */
    private static function put(Db $db, int $id, string $locale, ?int $parentId, ?int $position): void
    {
        $db->query('UPDATE pages SET parent_id = ? WHERE id = ?', [$parentId, $id]);
        $order = array_values(array_filter(self::siblings($db, $locale, $parentId), static fn (int $sibling): bool => $sibling !== $id));
        $at = $position === null ? count($order) : max(0, min($position, count($order)));
        array_splice($order, $at, 0, [$id]);
        foreach ($order as $sort => $sibling) {
            $db->query('UPDATE pages SET sort = ? WHERE id = ?', [$sort, $sibling]);
        }
    }

    /**
     * The sort of a page's source among the source's siblings, or null for a source itself
     * or a page whose group has none.
     */
    private static function sourceSort(Db $db, int $id): ?int
    {
        $row = $db->one(
            "SELECT s.sort FROM pages p JOIN pages s ON s.content_group_id = p.content_group_id AND s.translation_status = 'source'
             WHERE p.id = ? AND s.id <> p.id",
            [$id],
        );

        return $row === null ? null : (int) $row['sort'];
    }

    /**
     * @return list<int>
     */
    private static function siblings(Db $db, string $locale, ?int $parentId): array
    {
        $rows = $parentId === null
            ? $db->all('SELECT id FROM pages WHERE locale = ? AND parent_id IS NULL ORDER BY sort, title, id', [$locale])
            : $db->all('SELECT id FROM pages WHERE locale = ? AND parent_id = ? ORDER BY sort, title, id', [$locale, $parentId]);

        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }
}
