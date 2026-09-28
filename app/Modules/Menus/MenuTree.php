<?php

namespace App\Modules\Menus;

use App\Core\Db;
use App\Support\Url;

/**
 * The order of a menu's items, and the shape each side sees (PLAN.md D-028).
 *
 * Split from Menu the way PageTree is split from Page: one file stores items, this one
 * decides where they sit and what they resolve to. The ordering follows D-011 exactly —
 * the same two paths, a drag posting a whole sibling order and Up/Down posting one move —
 * because a second way of ordering things in one admin is a second set of bugs.
 */
final class MenuTree
{
    /**
     * What the ADMIN sees: every item, parents then their children, each with what it
     * resolves to and whether that is broken.
     *
     * A broken item is shown rather than hidden. Its page was deleted (0015 sets page_id
     * to NULL) or its address was refused, and the owner is the only one who can repoint
     * it — an item that vanished from the admin as well would leave them looking for
     * something that is not there.
     *
     * @return list<array{id: int, parent_id: int|null, depth: int, label: string, target: string,
     *                    page_id: int|null, page_title: string|null, published: bool, broken: bool,
     *                    hidden: bool, first: bool, last: bool}>
     */
    public static function admin(Db $db, int $menuId): array
    {
        $rows = $db->all(
            'SELECT i.*, p.title AS page_title, p.status AS page_status, p.locale AS page_locale, p.slug AS page_slug
             FROM menu_items i
             LEFT JOIN pages p ON p.id = i.page_id
             WHERE i.menu_id = ?
             ORDER BY i.sort, i.id',
            [$menuId],
        );

        $children = [];
        foreach ($rows as $row) {
            $children[$row['parent_id'] === null ? 0 : (int) $row['parent_id']][] = $row;
        }

        $listing = [];
        self::flatten($children, 0, 0, $listing);

        return $listing;
    }

    /**
     * What a VISITOR sees: only items that resolve to somewhere, nested one level.
     *
     * The rule D-028 asks for, in one place. An item is left out when it points at
     * nothing (its page was deleted, or its address was refused on save) and when the
     * page it points at is not published — the second is a state, not damage, and the
     * item comes back by itself when the page does. A parent that is left out takes its
     * children with it: a submenu hanging under nothing is not a menu.
     *
     * @return list<array{label: string, url: string, children: list<array{label: string, url: string}>}>
     */
    public static function forVisitors(Db $db, string $locale, string $name): array
    {
        $menu = $db->one('SELECT id FROM menus WHERE locale = ? AND name = ?', [$locale, $name]);
        if ($menu === null) {
            return [];
        }

        $resolved = [];
        foreach (self::admin($db, (int) $menu['id']) as $item) {
            if ($item['hidden']) {
                continue;
            }
            $resolved[$item['parent_id'] === null ? 0 : $item['parent_id']][] = $item;
        }

        $menuList = [];
        foreach ($resolved[0] ?? [] as $top) {
            $children = [];
            foreach ($resolved[$top['id']] ?? [] as $child) {
                $children[] = ['label' => $child['label'], 'url' => $child['target']];
            }
            $menuList[] = ['label' => $top['label'], 'url' => $top['target'], 'children' => $children];
        }

        return $menuList;
    }

    /**
     * Writes a whole sibling group's order, refusing anything that is not exactly that
     * group. Same guard PageTree::reorder uses: a posted list that drops or adds an id
     * would otherwise renumber a group into a shape nobody asked for.
     *
     * @param list<int> $ids
     */
    public static function reorder(Db $db, array $ids): bool
    {
        if ($ids === []) {
            return false;
        }
        $first = $db->one('SELECT menu_id, parent_id FROM menu_items WHERE id = ?', [$ids[0]]);
        if ($first === null) {
            return false;
        }
        $siblings = self::siblingIds($db, (int) $first['menu_id'], self::parentOf($first));
        if (count($ids) !== count($siblings) || array_diff($ids, $siblings) !== []) {
            return false;
        }
        foreach ($ids as $position => $id) {
            $db->query('UPDATE menu_items SET sort = ? WHERE id = ?', [$position, $id]);
        }

        return true;
    }

    /**
     * Swaps an item with the sibling above or below it: the no-JavaScript path, and the
     * only one a keyboard reaches. Rebuilt as a list rather than swapping two sort
     * values, so the order stays a list by construction.
     */
    public static function move(Db $db, int $id, string $direction): bool
    {
        $item = $db->one('SELECT menu_id, parent_id FROM menu_items WHERE id = ?', [$id]);
        if ($item === null) {
            return false;
        }
        $siblings = self::siblingIds($db, (int) $item['menu_id'], self::parentOf($item));
        $at = array_search($id, $siblings, true);
        if ($at === false) {
            return false;
        }
        $to = $direction === 'up' ? $at - 1 : $at + 1;
        if ($to < 0 || $to >= count($siblings)) {
            return false;
        }

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
     * @param array<int, list<array<string, mixed>>> $children
     * @param list<array{id: int, parent_id: int|null, depth: int, label: string, target: string,
     *                   page_id: int|null, page_title: string|null, published: bool, broken: bool,
     *                   hidden: bool, first: bool, last: bool}> $listing
     */
    private static function flatten(array $children, int $parent, int $depth, array &$listing): void
    {
        $group = $children[$parent] ?? [];
        $last = count($group) - 1;
        foreach ($group as $index => $row) {
            $id = (int) $row['id'];
            $listing[] = self::describe($row) + [
                'depth' => $depth,
                'first' => $index === 0,
                'last' => $index === $last,
            ];
            if ($depth < Menu::MAX_DEPTH) {
                self::flatten($children, $id, $depth + 1, $listing);
            }
        }
    }

    /**
     * One row as both sides need it: where it goes, what it is called, and whether it
     * goes anywhere at all.
     *
     * The address of a page is DERIVED, never stored: a page whose slug changes keeps its
     * menu entry pointing at the right place, which storing the address would not.
     *
     * The shape is written out here rather than left as array<string, mixed>. admin()
     * promises callers a precise row; a loose type inside means the promise is asserted at
     * the boundary and established nowhere, which is exactly what PHPStan caught.
     *
     * @param array<string, mixed> $row
     * @return array{id: int, parent_id: int|null, label: string, target: string, page_id: int|null,
     *               page_title: string|null, published: bool, broken: bool, hidden: bool}
     */
    private static function describe(array $row): array
    {
        $pageId = $row['page_id'] === null ? null : (int) $row['page_id'];
        $url = $row['url'] === null ? '' : (string) $row['url'];
        $title = $row['page_title'] === null ? null : (string) $row['page_title'];

        $target = '';
        if ($pageId !== null && $title !== null) {
            $target = Url::page((string) $row['page_locale'], (string) $row['page_slug']);
        } elseif ($url !== '') {
            $target = $url;
        }

        $label = (string) ($row['label'] ?? '');
        if ($label === '') {
            $label = $title ?? $url;
        }

        // `published` is ABOUT THE PAGE, not about the item, and is false for an item that
        // has no page at all — an address is neither published nor a draft. Reading it as
        // "this item is live" would put a "page is a draft" badge on every plain address,
        // which is what the first version of this did.
        //
        // `hidden` is the question both sides actually ask, decided once here so the
        // template and forVisitors() cannot answer it differently: an item is out when it
        // goes nowhere, and when the page it goes to is not published yet.
        $published = (string) ($row['page_status'] ?? '') === 'published';
        $broken = $target === '';

        return [
            'id' => (int) $row['id'],
            'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
            'label' => $label,
            'target' => $target,
            'page_id' => $pageId,
            'page_title' => $title,
            'published' => $published,
            'broken' => $broken,
            'hidden' => $broken || ($pageId !== null && !$published),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function parentOf(array $row): ?int
    {
        return $row['parent_id'] === null ? null : (int) $row['parent_id'];
    }

    /**
     * One sibling group in the order the screen shows it, so a swap moves an item past
     * the item it actually appears next to.
     *
     * @return list<int>
     */
    private static function siblingIds(Db $db, int $menuId, ?int $parentId): array
    {
        $rows = $parentId === null
            ? $db->all('SELECT id FROM menu_items WHERE menu_id = ? AND parent_id IS NULL ORDER BY sort, id', [$menuId])
            : $db->all('SELECT id FROM menu_items WHERE menu_id = ? AND parent_id = ? ORDER BY sort, id', [$menuId, $parentId]);

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }
}
