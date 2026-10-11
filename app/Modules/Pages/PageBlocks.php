<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\SectionStyle;
use App\Modules\Media\MediaReference;

/**
 * A PAGE'S BLOCKS AND SECTIONS, read and written (PLAN.md O-45): split from Page, which keeps
 * the page row itself — its address, title, place and status. Page::create() and
 * Page::update() write through here, inside their own transactions.
 *
 * All SQL is portable between MySQL and SQLite (SPEC §5.0).
 *
 * @phpstan-import-type BlockRow from Page
 */
final class PageBlocks
{
    /**
     * Stored blocks in order, exactly as stored: callers validate layout and style
     * against the registry when they use them.
     *
     * @return list<array{id: int, type: string, content: array<mixed>, style: array<mixed>, options: array<mixed>, layout: string, section: int, column: int}>
     */
    public static function stored(Db $db, int $pageId): array
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
            'SELECT b.id, b.block_type, b.content_json, b.options_json, s.style_json, b.layout, b.section_id, b.column_index
             FROM page_blocks b LEFT JOIN page_sections s ON s.id = b.section_id
             WHERE b.page_id = ? ORDER BY s.sort, b.column_index, b.sort, b.id',
            [$pageId],
        );
        foreach ($rows as $row) {
            $content = json_decode((string) $row['content_json'], true);
            $style = json_decode((string) ($row['style_json'] ?? ''), true);
            $options = json_decode((string) ($row['options_json'] ?? ''), true);
            $blocks[] = [
                'id' => (int) $row['id'],
                'type' => (string) $row['block_type'],
                'content' => is_array($content) ? $content : [],
                'style' => is_array($style) ? $style : [],
                // The owner's options, '' following the character (D-166).
                'options' => is_array($options) ? $options : [],
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
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string, section: string, column: int}>
     */
    public static function editable(Db $db, Blocks $registry, int $pageId): array
    {
        $blocks = [];
        foreach (self::stored($db, $pageId) as $block) {
            $known = $registry->has($block['type']);
            $blocks[] = [
                // A stored block's key is its id (D-094); nothing about it has to be
                // remembered between renders.
                'key' => BlockForm::key($block['id'], 0),
                'id' => $block['id'],
                'type' => $block['type'],
                'content' => $known ? $registry->normalize($block['type'], $block['content']) : null,
                'style' => SectionStyle::normalize($block['style']),
                'options' => $known ? \App\Core\BlockOptions::normalize($registry->get($block['type'])['options'], $block['options']) : [],
                'layout' => $known ? $registry->own($block['type'], $block['layout']) : '',
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
     * The block list and its sections written for a page whose row Page::update() has just
     * written: blocks with an id of this page are updated (never their type), blocks without
     * one are inserted, and this page's blocks missing from the list are deleted. Sections null
     * means one block, one section (SectionForm::oneEach); see Page::update().
     *
     * @param list<BlockRow> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     */
    public static function write(Db $db, Blocks $registry, int $id, array $blocks, ?array $sections, string $now): void
    {
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
                        'UPDATE page_blocks SET section_id = ?, column_index = ?, sort = ?, content_json = ?, options_json = ?, layout = ?, updated_at = ?
                         WHERE id = ? AND page_id = ?',
                        [
                            $target['id'],
                            $column,
                            $at,
                            self::json(MediaReference::resolve($db, $registry, $block['type'], $block['content'])),
                            self::json(self::options($registry, $block)),
                            $block['layout'],
                            $now,
                            $block['id'],
                            $id,
                        ],
                    );
                }
            } elseif ($block['content'] !== null) {
                self::insert($db, $registry, $id, $block, $target['id'], $column, $at, $now);
            }
        }
        foreach (array_keys($existing) as $blockId) {
            $db->query('DELETE FROM page_blocks WHERE id = ? AND page_id = ?', [$blockId, $id]);
        }
        // A section left holding nothing would go on drawing its surface and its rhythm
        // around an empty container.
        Sections::prune($db, $id);
    }

    /**
     * @param BlockRow $block
     * @param int $section the section it joins, already written
     * @param int $column  which of that section's columns
     * @param int $sort    its place DOWN that column
     */
    public static function insert(Db $db, Blocks $registry, int $pageId, array $block, int $section, int $column, int $sort, string $now): void
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
            'INSERT INTO page_blocks (page_id, section_id, column_index, block_type, sort, content_json, options_json, style_json, layout, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'{}\', ?, ?, ?)',
            [$pageId, $section, $column, $block['type'], $sort, self::json($content), self::json(self::options($registry, $block)), $block['layout'], $now, $now],
        );
        $blockId = (int) $db->lastInsertId();
        $db->query('UPDATE page_blocks SET block_group_id = id WHERE id = ?', [$blockId]);
    }

    /**
     * A block's options as stored: only those the owner set (D-166). A type this install no
     * longer has keeps none.
     *
     * @param array<string, mixed> $block
     * @return array<string, string>
     */
    private static function options(Blocks $registry, array $block): array
    {
        $type = (string) ($block['type'] ?? '');
        if (!$registry->has($type)) {
            return [];
        }

        return array_filter(\App\Core\BlockOptions::normalize($registry->get($type)['options'], $block['options'] ?? []), static fn (string $value): bool => $value !== '');
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
