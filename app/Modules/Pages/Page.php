<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Menus\Menu;
use App\Modules\Redirects\Redirects;

/**
 * Pages: the page row itself — its address, title, place among its siblings and status. Its
 * blocks and sections are read and written by PageBlocks (O-45). All SQL is portable between
 * MySQL and SQLite (SPEC §5.0).
 *
 * @phpstan-type BlockRow array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}
 */
final class Page
{
    /**
     * @return array<string, mixed>|null
     */
    public static function find(Db $db, int $id): ?array
    {
        return $db->one('SELECT * FROM pages WHERE id = ?', [$id]);
    }

    /**
     * The page visitors see at $slug in $locale, or null when missing or unpublished.
     *
     * @return array<string, mixed>|null
     */
    public static function published(Db $db, string $locale, string $slug): ?array
    {
        return $db->one(
            'SELECT * FROM pages WHERE locale = ? AND slug = ? AND status = ?',
            [$locale, $slug, 'published'],
        );
    }

    /**
     * Creates a draft page with empty blocks of the given types, each following the
     * character: drawn as whatever character the site has composes that block type (SPEC
     * §5.4, D-191). The page and each block start their own groups.
     *
     * @param list<string> $blockTypes
     */
    public static function create(Db $db, Blocks $registry, string $locale, string $title, string $slug, ?int $templateId, array $blockTypes): int
    {
        return $db->transaction(static function () use ($db, $registry, $locale, $title, $slug, $templateId, $blockTypes): int {
            $now = gmdate('Y-m-d H:i:s');
            // A new page goes last among its siblings. Without this every page keeps the
            // column's default of 0, they all tie, and the order a drag just set is
            // decided by the title tie-break instead (PLAN.md D-011). New pages are
            // top-level: the parent is chosen afterwards, in page settings.
            $next = PageTree::nextSort($db, $locale, null);
            $db->query(
                'INSERT INTO pages (locale, slug, title, status, template_id, sort, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$locale, $slug, $title, 'draft', $templateId, $next, $now, $now],
            );
            $id = (int) $db->lastInsertId();
            $db->query('UPDATE pages SET content_group_id = id WHERE id = ?', [$id]);
            Redirects::claimed($db, $locale, $slug);
            PagePaths::changed();
            foreach ($blockTypes as $sort => $type) {
                $block = [
                    // Never rendered in an editor, so the key only has to exist and differ.
                    'key' => BlockForm::key(null, $sort),
                    'id' => null,
                    'type' => $type,
                    'content' => $registry->fresh($type),
                    'style' => \App\Modules\Design\SectionStyle::normalize([]), // every key '' — the character's (D-165)
                    // Following the character (D-191): drawn in its composition's layout.
                    'layout' => '',
                ];
                // One block, one section, in its only column — a new page has no
                // arrangement to express yet, and the template that gives it one will say
                // so by passing sections to update() rather than by growing this loop.
                $section = Sections::save($db, $id, null, $sort, $block['style'], $now);
                PageBlocks::insert($db, $registry, $id, $block, $section, 0, 0, $now);
            }

            return $id;
        });
    }

    /**
     * Saves the whole page: its settings, and the block list in order. Blocks with an id
     * of this page are updated (never their type), blocks without one are inserted, and
     * this page's blocks missing from the list are deleted. A null content keeps
     * everything stored, for blocks whose type is no longer installed.
     *
     * The settings travel as one array rather than as a growing list of parameters,
     * because every caller has to pass all of them: a save that left one out would write
     * a default over whatever the page already had.
     *
     * The registry is here because saving resolves media references: which fields of a
     * block can hold a picture is something only the registry knows.
     *
     * seo_json travels already encoded, by PageSeo::json(), because it is also what
     * a rejected save hands back to the editor: one name for the column, the settings
     * array and the re-rendered form means those three can never drift apart.
     *
     * THE SECTIONS ARE OPTIONAL, and null means "one block, one section, arrangement left
     * as it is" (SectionForm::oneEach). The demo seed, the test fixtures and every caller
     * written before columns existed have nothing to say about an arrangement, and making
     * each of them say it would put the same sentence in a dozen places. A caller that
     * DOES send sections is believed completely: their order is the page's order, and a
     * block belongs to the section its key names.
     *
     * @param array{title: string, slug: string, parent_id: int|null, status: string, seo_json: string} $page
     * @param list<BlockRow> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     */
    public static function update(Db $db, Blocks $registry, int $id, array $page, array $blocks, ?array $sections = null): void
    {
        $db->transaction(static function () use ($db, $registry, $id, $page, $blocks, $sections): void {
            $now = gmdate('Y-m-d H:i:s');
            // A page that changes parent joins a different set of siblings, where its old
            // position means nothing and collides with whoever already holds it. It goes
            // last in the group it arrives in, the same rule a new page follows.
            $current = $db->one('SELECT locale, slug, parent_id, sort, published_at FROM pages WHERE id = ?', [$id]);
            $sort = (int) ($current['sort'] ?? 0);
            if ($current !== null && (int) ($current['parent_id'] ?? 0) !== (int) ($page['parent_id'] ?? 0)) {
                $sort = PageTree::nextSort($db, (string) $current['locale'], $page['parent_id']);
            }
            // published_at is stamped the first time a page is published and kept after
            // that, the same rule setStatus() follows.
            $db->query(
                'UPDATE pages SET title = ?, slug = ?, parent_id = ?, sort = ?, status = ?, seo_json = ?,
                        published_at = COALESCE(published_at, ?), updated_at = ?
                 WHERE id = ?',
                [
                    $page['title'],
                    $page['slug'],
                    $page['parent_id'],
                    $sort,
                    $page['status'],
                    $page['seo_json'],
                    $page['status'] === 'published' ? $now : null,
                    $now,
                    $id,
                ],
            );
            // The old slug of a page visitors could have known keeps leading here (D-129).
            PagePaths::changed();
            if ($current !== null) {
                Redirects::slugChanged($db, $id, (string) $current['locale'], (string) $current['slug'], $page['slug'], $current['published_at'] !== null);
            }

            PageBlocks::write($db, $registry, $id, $blocks, $sections, $now);
        });
    }

    public static function setStatus(Db $db, int $id, bool $published): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $db->query(
            'UPDATE pages SET status = ?, published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ?',
            [$published ? 'published' : 'draft', $published ? $now : null, $now, $id],
        );
    }

    /**
     * Deletes the page; its blocks go with it through the foreign key, and its old
     * addresses with it by hand, so they answer 404 rather than lead nowhere (D-129), and
     * the menu items that led to it (D-218).
     */
    public static function delete(Db $db, int $id): void
    {
        Redirects::pageDeleted($db, $id);
        Menu::pageDeleted($db, $id);
        $db->query('DELETE FROM pages WHERE id = ?', [$id]);
        PagePaths::changed();
    }

    /**
     * @return list<array{id: int, name: string, builtin: bool, blocks: list<string>}>
     */
    public static function templates(Db $db): array
    {
        $templates = [];
        foreach ($db->all('SELECT id, name, layout_json, is_builtin FROM templates ORDER BY id') as $row) {
            $layout = json_decode((string) $row['layout_json'], true);
            $types = is_array($layout) && is_array($layout['blocks'] ?? null) ? $layout['blocks'] : [];
            $templates[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'builtin' => (int) $row['is_builtin'] === 1,
                'blocks' => array_values(array_filter($types, 'is_string')),
            ];
        }

        return $templates;
    }
}
