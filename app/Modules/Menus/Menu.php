<?php

namespace App\Modules\Menus;

use App\Core\Db;
use App\Support\SafeUrl;

/**
 * Menus and their items (PLAN.md D-028, migration 0015).
 *
 * A menu is not the page tree. The tree says what is under what and orders the admin
 * list (D-011); a menu says what a visitor is offered and what it is called there. Most
 * sites answer those two questions differently, which is why this is built by hand.
 *
 * A menu belongs to one locale, so a translation has its own labels rather than borrowing
 * the source language's, and the pages it may point at are that locale's pages.
 *
 * Ordering and the front-end shape live in MenuTree, the same split Pages/PageTree uses.
 */
final class Menu
{
    /** One level of submenu, and no more (D-028). Enforced here: SQL cannot say it. */
    public const MAX_DEPTH = 1;

    /**
     * Every menu, ordered for the admin list.
     *
     * @return list<array{id: int, locale: string, name: string, items: int}>
     */
    public static function all(Db $db): array
    {
        $counts = [];
        foreach ($db->all('SELECT menu_id, COUNT(*) AS n FROM menu_items GROUP BY menu_id') as $row) {
            $counts[(int) $row['menu_id']] = (int) $row['n'];
        }

        $menus = [];
        foreach ($db->all('SELECT id, locale, name FROM menus ORDER BY locale, name, id') as $row) {
            $id = (int) $row['id'];
            $menus[] = [
                'id' => $id,
                'locale' => (string) $row['locale'],
                'name' => (string) $row['name'],
                'items' => $counts[$id] ?? 0,
            ];
        }

        return $menus;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(Db $db, int $id): ?array
    {
        return $db->one('SELECT * FROM menus WHERE id = ?', [$id]);
    }

    public static function create(Db $db, string $locale, string $name): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $db->query(
            'INSERT INTO menus (locale, name, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$locale, $name, $now, $now],
        );

        return (int) $db->lastInsertId();
    }

    public static function rename(Db $db, int $id, string $name): void
    {
        $db->query('UPDATE menus SET name = ?, updated_at = ? WHERE id = ?', [$name, gmdate('Y-m-d H:i:s'), $id]);
    }

    /**
     * Deletes a menu; its items go with it through the foreign key (0015 is CASCADE for
     * menu_id, because an item has no meaning without the menu it belongs to).
     */
    public static function delete(Db $db, int $id): void
    {
        $db->query('DELETE FROM menus WHERE id = ?', [$id]);
    }

    /**
     * Whether a name is free in its locale. The database enforces it too; this exists so
     * the screen can say so in words instead of showing a constraint violation.
     */
    public static function nameTaken(Db $db, string $locale, string $name, ?int $exceptId = null): bool
    {
        $row = $exceptId === null
            ? $db->one('SELECT id FROM menus WHERE locale = ? AND name = ?', [$locale, $name])
            : $db->one('SELECT id FROM menus WHERE locale = ? AND name = ? AND id <> ?', [$locale, $name, $exceptId]);

        return $row !== null;
    }

    /**
     * The pages a menu of this locale may point at: that locale's, in the admin's order.
     *
     * Drafts are offered as well as published ones. A menu is usually built before the
     * pages in it go live, and a chooser that hid them would send the owner away to
     * publish first and come back — while the front end already leaves an unpublished
     * item out, so nothing broken can reach a visitor either way.
     *
     * Each carries its address too, so choosing one can show where it leads (D-038).
     *
     * @return list<array{id: int, title: string, status: string, url: string}>
     */
    public static function pageChoices(Db $db, string $locale): array
    {
        $pages = [];
        foreach ($db->all('SELECT id, title, status, slug FROM pages WHERE locale = ? ORDER BY sort, title, id', [$locale]) as $row) {
            $pages[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'url' => \App\Support\Url::page($locale, (string) $row['slug']),
            ];
        }

        return $pages;
    }

    /**
     * Adds an item to a menu, at the end of its sibling group.
     *
     * A parent that belongs to another menu, or that is itself a child, is refused: one
     * level of submenu is the rule, and a cycle is not expressible in this shape but a
     * wrong menu_id is.
     *
     * @return int|null the new id, or null when the parent is not a valid one
     */
    public static function addItem(
        Db $db,
        int $menuId,
        ?int $parentId,
        ?int $pageId,
        ?string $url,
        ?string $label,
    ): ?int {
        if ($parentId !== null && !self::canParent($db, $menuId, $parentId)) {
            return null;
        }
        $pageId = self::pageIn($db, $menuId, $pageId);

        $now = gmdate('Y-m-d H:i:s');
        $db->query(
            'INSERT INTO menu_items (menu_id, parent_id, page_id, url, label, sort, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$menuId, $parentId, $pageId, self::cleanUrl($url), self::cleanLabel($label), self::nextSort($db, $menuId, $parentId), $now, $now],
        );

        return (int) $db->lastInsertId();
    }

    /**
     * Changes what an item points at and what it is called. A page and an address are
     * exclusive: choosing a page clears the address, and vice versa, so an item never
     * holds two destinations and leaves the reader to guess which one wins.
     */
    public static function updateItem(Db $db, int $id, ?int $pageId, ?string $url, ?string $label): void
    {
        $item = $db->one('SELECT menu_id FROM menu_items WHERE id = ?', [$id]);
        $pageId = $item === null ? null : self::pageIn($db, (int) $item['menu_id'], $pageId);
        $url = $pageId !== null ? null : self::cleanUrl($url);
        $db->query(
            'UPDATE menu_items SET page_id = ?, url = ?, label = ?, updated_at = ? WHERE id = ?',
            [$pageId, $url, self::cleanLabel($label), gmdate('Y-m-d H:i:s'), $id],
        );
    }

    public static function deleteItem(Db $db, int $id): void
    {
        $db->query('DELETE FROM menu_items WHERE id = ?', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findItem(Db $db, int $id): ?array
    {
        return $db->one('SELECT * FROM menu_items WHERE id = ?', [$id]);
    }

    /**
     * An address the admin typed, or null. Anything SafeUrl refuses is stored as null
     * rather than kept: the same rule the block editor's link fields follow (SPEC §5.3),
     * through the same class, so the two cannot drift into two ideas of a safe URL.
     */
    private static function cleanUrl(?string $url): ?string
    {
        $url = SafeUrl::normalize($url ?? '');

        return $url !== '' && SafeUrl::isAllowed($url) ? $url : null;
    }

    private static function cleanLabel(?string $label): ?string
    {
        $label = $label === null ? '' : trim($label);

        return $label === '' ? null : mb_substr($label, 0, 255);
    }

    /**
     * The page id if it names a page in this menu's locale, otherwise null.
     *
     * TWO FAILURES THIS CLOSES, both of which reached the database before it existed.
     * A page deleted between opening the form and saving it would have made the insert
     * throw a foreign key violation — an SQL error on screen instead of an item that
     * points nowhere, which is what D-028 asks for. And a page id from ANOTHER locale
     * would have been stored happily: the chooser only offers this locale's pages, but a
     * chooser is not a rule, and a menu belongs to one locale.
     *
     * The same shape MediaReference sets for pictures: an id that names nothing becomes
     * null on save rather than being kept and rendered as damage.
     */
    private static function pageIn(Db $db, int $menuId, ?int $pageId): ?int
    {
        if ($pageId === null) {
            return null;
        }
        $row = $db->one(
            'SELECT p.id FROM pages p JOIN menus m ON m.locale = p.locale WHERE p.id = ? AND m.id = ?',
            [$pageId, $menuId],
        );

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * True when $parentId may hold children in $menuId: it exists, belongs to this menu,
     * and is itself top level.
     */
    private static function canParent(Db $db, int $menuId, int $parentId): bool
    {
        $parent = $db->one('SELECT menu_id, parent_id FROM menu_items WHERE id = ?', [$parentId]);

        return $parent !== null
            && (int) $parent['menu_id'] === $menuId
            && $parent['parent_id'] === null;
    }

    private static function nextSort(Db $db, int $menuId, ?int $parentId): int
    {
        $row = $parentId === null
            ? $db->one('SELECT COALESCE(MAX(sort), -1) + 1 AS next FROM menu_items WHERE menu_id = ? AND parent_id IS NULL', [$menuId])
            : $db->one('SELECT COALESCE(MAX(sort), -1) + 1 AS next FROM menu_items WHERE menu_id = ? AND parent_id = ?', [$menuId, $parentId]);

        return (int) ($row['next'] ?? 0);
    }
}
