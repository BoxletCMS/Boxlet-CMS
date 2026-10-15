<?php

namespace App\Modules\Menus;

use App\Core\Db;
use App\Modules\Pages\Translations;
use App\Support\Url;

/**
 * A LANGUAGE'S MENUS FROM ANOTHER'S (PLAN.md D-262, the owner): every menu of one language
 * mirrored into another under the same name, the header and footer choosing menus by name
 * (D-030). Run when a page is copied into a language, and by "Match the main language" on
 * Navigation for what changed later.
 *
 * WHAT IS ADDED, NEVER WHAT IS THERE: an item the original menu has and the copy does not gets
 * one, under the copy of its parent, in the original's order. Nothing is moved, renamed or
 * removed, so a menu the owner arranged by hand stays theirs. Each copy remembers its original
 * (source_item_id, migration 0045), which is how the next run knows it is there.
 *
 * WHERE AN ITEM LEADS: a page's item leads to that page's version in the language; while there
 * is none, the item waits, and the next copy of a page brings it. An item under another waits
 * for its parent's copy in the same way, so the shape is always the original's. An address leads where it did,
 * unless it is the address of one of the site's pages, typed by hand: then it becomes that
 * page's item. An item whose version is a draft is there but not shown (MenuTree), so the menu
 * fills as the owner publishes.
 *
 * WHAT IT IS CALLED: a page's item has no label of its own, so it shows its page's title in the
 * language; an address keeps the original's label until AI translation says it in the language
 * (AiNavigation). An item of the owner's that already leads to the page is adopted rather than
 * doubled, so the items under it find their parent.
 */
final class MenuCopy
{
    /** Every menu of $from into $to. Returns how many items were added. */
    public static function sync(Db $db, string $from, string $to): int
    {
        if ($from === $to) {
            return 0;
        }
        $added = 0;
        foreach ($db->all('SELECT id, name FROM menus WHERE locale = ? ORDER BY id', [$from]) as $menu) {
            $added += self::menu($db, (int) $menu['id'], (string) $menu['name'], $from, $to);
        }

        return $added;
    }

    private static function menu(Db $db, int $sourceMenu, string $name, string $from, string $to): int
    {
        // Parents before their children, each group in the original's order.
        $items = $db->all('SELECT * FROM menu_items WHERE menu_id = ? ORDER BY CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END, sort, id', [$sourceMenu]);
        $menu = $db->one('SELECT id FROM menus WHERE locale = ? AND name = ?', [$to, $name]);
        $menuId = $menu === null ? null : (int) $menu['id'];

        $copies = [];
        $owners = [];
        foreach ($menuId === null ? [] : $db->all('SELECT id, parent_id, page_id, source_item_id FROM menu_items WHERE menu_id = ?', [$menuId]) as $row) {
            if ($row['source_item_id'] !== null) {
                $copies[(int) $row['source_item_id']] = (int) $row['id'];
            } elseif ($row['page_id'] !== null) {
                $owners[(int) $row['page_id']] = ['id' => (int) $row['id'], 'top' => $row['parent_id'] === null];
            }
        }

        $added = 0;
        foreach ($items as $item) {
            $sourceId = (int) $item['id'];
            if (isset($copies[$sourceId])) {
                continue;
            }
            [$pageId, $url] = self::destination($db, $item, $from, $to);
            if ($pageId === null && $url === null) {
                continue;
            }
            // A child waits for its parent: put at the top meanwhile, it would stay there, and
            // the menu would not have the original's shape (found by the test that ticks a
            // subpage first). The next copy, or Match, places it.
            if ($item['parent_id'] !== null && !isset($copies[(int) $item['parent_id']])) {
                continue;
            }
            $parent = $item['parent_id'] === null ? null : $copies[(int) $item['parent_id']];
            // The owner's own item for this page, where it stands as this one would: adopted.
            if ($pageId !== null && isset($owners[$pageId]) && $owners[$pageId]['top'] === ($item['parent_id'] === null)) {
                $db->query('UPDATE menu_items SET source_item_id = ? WHERE id = ?', [$sourceId, $owners[$pageId]['id']]);
                $copies[$sourceId] = $owners[$pageId]['id'];
                unset($owners[$pageId]);
                continue;
            }
            if ($menuId === null) {
                $menuId = Menu::create($db, $to, $name);
            }
            $now = gmdate('Y-m-d H:i:s');
            $db->query(
                'INSERT INTO menu_items (menu_id, parent_id, page_id, url, label, sort, source_item_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$menuId, $parent, $pageId, $url, $pageId === null ? $item['label'] : null, (int) $item['sort'], $sourceId, $now, $now],
            );
            $copies[$sourceId] = (int) $db->lastInsertId();
            $added++;
        }

        return $added;
    }

    /**
     * Where the copy of an item leads in $to: [page id, null], [null, address], or [null, null]
     * while it can lead nowhere yet.
     *
     * @param array<string, mixed> $item
     * @return array{0: int|null, 1: string|null}
     */
    private static function destination(Db $db, array $item, string $from, string $to): array
    {
        if ($item['page_id'] !== null) {
            return [Translations::of($db, (int) $item['page_id'])[$to] ?? null, null];
        }
        $url = (string) ($item['url'] ?? '');
        if ($url === '') {
            return [null, null];
        }
        // An address typed by hand that is one of the site's own pages in the original language.
        foreach ($db->all('SELECT id, slug FROM pages WHERE locale = ?', [$from]) as $page) {
            if (rtrim(Url::page($from, (string) $page['slug']), '/') === rtrim($url, '/')) {
                return [Translations::of($db, (int) $page['id'])[$to] ?? null, null];
            }
        }

        return [null, $url];
    }
}
