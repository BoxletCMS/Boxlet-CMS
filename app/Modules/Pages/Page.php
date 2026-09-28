<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\Composition;
use App\Modules\Design\SectionStyle;
use App\Modules\Media\MediaReference;
use App\Modules\Redirects\Redirects;

/**
 * Pages and their blocks. All SQL is portable between MySQL and SQLite (SPEC §5.0).
 *
 * @phpstan-type BlockRow array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}
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
     * What this page says about itself in <head>: a title and a description (D-004).
     * Two fields, and nothing else — no sharing image, no robots directive, no sitemap.
     *
     * WHAT IS STORED, NOT WHAT IS SHOWN. Neither field falls back here, deliberately.
     * The title's fallback to the page title belongs where it is rendered, because a
     * fallback applied here would be written straight back on the next save: the page
     * title would become an explicit SEO title and would stop following the title from
     * then on. The editors want the raw value too, or an owner cannot tell a field they
     * set from one they inherited.
     *
     * A page created before this existed — and every new page, since create() does not
     * write the column — has NULL rather than '{}', so both have to decode to nothing.
     *
     * @param array<string, mixed> $page a row from find() or published()
     * @return array{title: string, description: string}
     */
    public static function seo(array $page): array
    {
        $stored = json_decode((string) ($page['seo_json'] ?? ''), true);
        $stored = is_array($stored) ? $stored : [];

        return [
            'title' => is_string($stored['title'] ?? null) ? trim($stored['title']) : '',
            'description' => is_string($stored['description'] ?? null) ? trim($stored['description']) : '',
        ];
    }

    /**
     * The inverse of seo(), kept beside it: both halves of one stored shape belong
     * together, and the alternative is json_encode's flags copied into a controller,
     * where they drift.
     *
     * An empty field is dropped rather than stored as an empty string, so a page with no
     * SEO of its own holds {} however it arrived there.
     *
     * @param array{title: string, description: string} $seo
     */
    public static function seoJson(array $seo): string
    {
        return self::json(array_filter(
            ['title' => trim($seo['title']), 'description' => trim($seo['description'])],
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * Stored blocks in order, exactly as stored: callers validate layout and style
     * against the registry when they use them.
     *
     * @return list<array{id: int, type: string, content: array<mixed>, style: array<mixed>, layout: string, section: int, column: int}>
     */
    public static function blocks(Db $db, int $pageId): array
    {
        /*
         * THE STYLE COMES FROM THE SECTION (D-095), joined rather than fetched per block:
         * a page draws in a fixed number of queries or it does not scale.
         *
         * The order is the section's place on the page, then the block's place inside it.
         * While a section holds one block those are the same order this always returned.
         *
         * A LEFT join, and a null style falls to the defaults: a block without a section
         * cannot happen after migration 0026, and if a later bug makes one, it draws plainly
         * rather than not at all.
         */
        $blocks = [];
        $rows = $db->all(
            'SELECT b.id, b.block_type, b.content_json, s.style_json, b.layout, b.section_id, b.column_index
             FROM page_blocks b LEFT JOIN page_sections s ON s.id = b.section_id
             WHERE b.page_id = ? ORDER BY s.sort, b.column_index, b.sort, b.id',
            [$pageId],
        );
        foreach ($rows as $row) {
            $content = json_decode((string) $row['content_json'], true);
            $style = json_decode((string) ($row['style_json'] ?? ''), true);
            $blocks[] = [
                'id' => (int) $row['id'],
                'type' => (string) $row['block_type'],
                'content' => is_array($content) ? $content : [],
                'style' => is_array($style) ? $style : [],
                'layout' => (string) $row['layout'],
                // Which section, and which of its columns (D-093 step 3). The order above
                // reads column before sort for the reason a newspaper is read that way: a
                // column is finished before the next one starts, so a flat list of a page's
                // blocks is the order somebody reads them in.
                'section' => $row['section_id'] === null ? 0 : (int) $row['section_id'],
                'column' => (int) $row['column_index'],
            ];
        }

        return $blocks;
    }

    /**
     * This page's blocks as an editor needs them: content and layout normalised against
     * the registry, style normalised against the closed sets, and a block whose type is
     * no longer installed kept with a null content so saving cannot discard it.
     *
     * Both editors render from this, so the visual canvas and the fallback form always
     * agree about what is on the page.
     *
     * EACH BLOCK SAYS WHERE IT STANDS (D-098): the KEY of its section and the column inside
     * it. A key and not an id, because the editor's two shapes have to meet — a block added
     * in this session names a section that has no id yet, and a section the editor has just
     * made has to be nameable by the blocks put into it before anything is saved.
     *
     * The style is still on the block AND on the section, deliberately and for one more
     * step: every screen in the editor reads it off the block, and moving that read is the
     * next commit. Both come from the same section row, so they cannot disagree.
     *
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section: string, column: int}>
     */
    public static function editable(Db $db, Blocks $registry, int $pageId): array
    {
        $blocks = [];
        foreach (self::blocks($db, $pageId) as $block) {
            $known = $registry->has($block['type']);
            $blocks[] = [
                // A stored block's key is its id (D-094); nothing about it has to be
                // remembered between renders.
                'key' => BlockForm::key($block['id'], 0),
                'id' => $block['id'],
                'type' => $block['type'],
                'content' => $known ? $registry->normalize($block['type'], $block['content']) : null,
                'style' => SectionStyle::normalize($block['style']),
                'layout' => $known ? $registry->layout($block['type'], $block['layout']) : '',
                'section' => SectionForm::key($block['section'] > 0 ? $block['section'] : null, 0),
                'column' => $block['column'],
            ];
        }

        return $blocks;
    }

    /**
     * This page's sections as an editor needs them, in page order.
     *
     * Beside editable() rather than inside it, and so a second query: every screen and every
     * stored shape in the editor is a flat list of blocks, and a tree would have to be
     * unpicked again by all of them. The front end already works this way — a flat list
     * joined to a map of sections at the moment of drawing (Sections::group) — and one
     * arrangement of the same facts is worth more than the query.
     *
     * @return list<array{key: string, id: int|null, layout: string, stack: string, style: array<string, string|int|null>}>
     */
    public static function editableSections(Db $db, int $pageId): array
    {
        $sections = [];
        foreach (Sections::forPage($db, $pageId) as $id => $section) {
            $sections[] = [
                'key' => SectionForm::key($id, 0),
                'id' => $id,
                'layout' => $section['layout'],
                'stack' => $section['stack'],
                'style' => $section['style'],
            ];
        }

        return $sections;
    }

    /**
     * Creates a draft page with empty blocks of the given types, each composed as the
     * active character composes that block type (SPEC §5.4). The page and each block
     * start their own groups.
     *
     * @param list<string> $blockTypes
     * @param string|null  $character   the character new blocks start from; null for the
     *                                  block's own defaults
     */
    public static function create(Db $db, Blocks $registry, string $locale, string $title, string $slug, ?int $templateId, array $blockTypes, ?string $character = null): int
    {
        return $db->transaction(static function () use ($db, $registry, $locale, $title, $slug, $templateId, $blockTypes, $character): int {
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
                    'style' => Composition::style($character, $type),
                    'layout' => Composition::layout($registry, $character, $type),
                ];
                // One block, one section, in its only column — a new page has no
                // arrangement to express yet, and the template that gives it one will say
                // so by passing sections to update() rather than by growing this loop.
                $section = Sections::save($db, $id, null, $sort, $block['style'], $now);
                self::insertBlock($db, $registry, $id, $block, $section, 0, 0, $now);
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
     * seo_json travels already encoded, by Page::seoJson(), because it is also what
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

            /*
             * THE SECTIONS ARE WRITTEN FIRST, so every block row has one to point at, and
             * their submitted order is the page's order (D-093 step 3). A block's own sort
             * is its place DOWN its column — not its place on the page, which stopped being
             * true at D-095 and stops being expressible at all now that two blocks can
             * stand side by side.
             */
            $existing = [];
            foreach ($db->all('SELECT id, section_id FROM page_blocks WHERE page_id = ?', [$id]) as $row) {
                $existing[(int) $row['id']] = $row['section_id'] === null ? null : (int) $row['section_id'];
            }
            if ($sections === null) {
                ['sections' => $sections, 'blocks' => $blocks] = SectionForm::oneEach($blocks, $existing);
            }

            $held = [];
            foreach ($sections as $sort => $section) {
                $sectionId = Sections::save(
                    $db,
                    $id,
                    $section['id'],
                    $sort,
                    $section['style'],
                    $now,
                    $section['layout'],
                    $section['stack'],
                );
                $held[$section['key']] = ['id' => $sectionId, 'layout' => $section['layout']];
            }

            // WHERE EACH BLOCK LANDS. The column is clamped to what its section actually
            // has, and the place down that column is counted as the blocks go by, so the
            // submitted order within a column is the stored order — the same rule the page
            // order has always followed, one level down.
            $slot = [];
            foreach ($blocks as $ordinal => $block) {
                $target = $held[$block['section'] ?? ''] ?? null;
                if ($target === null) {
                    // A block naming no section anybody sent. Rather than dropping it or
                    // guessing a neighbour, it is given one of its own at the end of the
                    // page: visible, obviously wrong, and nothing is lost.
                    $target = [
                        'id' => Sections::save($db, $id, null, count($sections) + $ordinal, $block['style'], $now),
                        'layout' => SectionLayout::ONE,
                    ];
                }
                $column = SectionLayout::clamp((int) ($block['column'] ?? 0), $target['layout']);
                $at = $slot[$target['id']][$column] ?? 0;
                $slot[$target['id']][$column] = $at + 1;

                if ($block['id'] !== null && array_key_exists($block['id'], $existing)) {
                    unset($existing[$block['id']]);
                    // A block whose type is no longer installed keeps its content untouched;
                    // only where it sits can still be changed.
                    if ($block['content'] === null) {
                        $db->query(
                            'UPDATE page_blocks SET section_id = ?, column_index = ?, sort = ? WHERE id = ? AND page_id = ?',
                            [$target['id'], $column, $at, $block['id'], $id],
                        );
                    } else {
                        $db->query(
                            'UPDATE page_blocks SET section_id = ?, column_index = ?, sort = ?, content_json = ?, layout = ?, updated_at = ?
                             WHERE id = ? AND page_id = ?',
                            [
                                $target['id'],
                                $column,
                                $at,
                                self::json(MediaReference::resolve($db, $registry, $block['type'], $block['content'])),
                                $block['layout'],
                                $now,
                                $block['id'],
                                $id,
                            ],
                        );
                    }
                } elseif ($block['content'] !== null) {
                    self::insertBlock($db, $registry, $id, $block, $target['id'], $column, $at, $now);
                }
            }
            foreach (array_keys($existing) as $blockId) {
                $db->query('DELETE FROM page_blocks WHERE id = ? AND page_id = ?', [$blockId, $id]);
            }
            // A section left holding nothing would go on drawing its surface and its rhythm
            // around an empty container.
            Sections::prune($db, $id);
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
     * addresses with it by hand, so they answer 404 rather than lead nowhere (D-129).
     */
    public static function delete(Db $db, int $id): void
    {
        Redirects::pageDeleted($db, $id);
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

    /**
     * @param BlockRow $block
     * @param int $section the section it joins, already written
     * @param int $column  which of that section's columns
     * @param int $sort    its place DOWN that column
     */
    private static function insertBlock(Db $db, Blocks $registry, int $pageId, array $block, int $section, int $column, int $sort, string $now): void
    {
        // Pictures are validated here rather than in normalize(), which is pure and called
        // from places with no database (D-024, extended to block content). An id naming a
        // picture that does not exist becomes null: a section renders as it did before
        // pictures existed, and a block shows its placeholder. Leaving a dangling id would
        // let the next picture to take that number be adopted by the page silently.
        $content = MediaReference::resolve($db, $registry, $block['type'], $block['content'] ?? []);
        // The section it was told to join, written by the caller before any block was.
        // style_json is written empty and never read again — migration 0026 left the column
        // in place because a committed migration is not edited, and a test refuses to find
        // it read anywhere.
        $db->query(
            'INSERT INTO page_blocks (page_id, section_id, column_index, block_type, sort, content_json, style_json, layout, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, \'{}\', ?, ?, ?)',
            [$pageId, $section, $column, $block['type'], $sort, self::json($content), $block['layout'], $now, $now],
        );
        $blockId = (int) $db->lastInsertId();
        $db->query('UPDATE page_blocks SET block_group_id = id WHERE id = ?', [$blockId]);
    }

    /**
     * Encodes content or style. An empty style is stored as an object, not a list.
     *
     * @param array<string, mixed> $value
     */
    private static function json(array $value): string
    {
        return $value === [] ? '{}' : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
