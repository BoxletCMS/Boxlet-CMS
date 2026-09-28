<?php

namespace App\Modules\Pages;

use App\Core\Db;
use App\Modules\Design\SectionStyle;

/**
 * The section a block sits in, and the layer-2 style it owns (PLAN.md D-093, migration 0026).
 *
 * A page is a list of sections; a section holds blocks in its columns; depth is exactly two.
 * This class is the RECORD — what a page's sections are, and how they are written, copied
 * and pruned. What one looks like is SectionRender, and how many columns it has and in what
 * proportion is SectionLayout.
 *
 * WHY THE STYLE BELONGS HERE. surface, rhythm, width, align, divider and the background
 * picture have always been section language applied per block, because there was nothing
 * else to hang them on. A tinted band of three text blocks meant setting one style three
 * times and hoping they stayed in step.
 *
 * THE BLOCK CONTRACT IS UNTOUCHED, which is what makes the whole change affordable: a block
 * is still a leaf with fields, and every definition, template, validator and repeater is
 * unaffected. Nothing in this file knows what a block type is.
 */
final class Sections
{
    /**
     * Every section of a page: id => ['sort', 'layout', 'stack', 'style'].
     *
     * Read in one statement rather than per block, for the reason MediaPicture::forBlocks()
     * gives about pictures: a page draws in a fixed number of queries or it does not scale.
     *
     * @return array<int, array{sort: int, layout: string, stack: string, style: array<string, string|int|null>}>
     */
    public static function forPage(Db $db, int $pageId): array
    {
        $sections = [];
        foreach ($db->all(
            'SELECT id, sort, layout, stack, style_json FROM page_sections WHERE page_id = ? ORDER BY sort, id',
            [$pageId],
        ) as $row) {
            $style = json_decode((string) $row['style_json'], true);
            $sections[(int) $row['id']] = [
                'sort' => (int) $row['sort'],
                'layout' => SectionLayout::normalize($row['layout']),
                'stack' => SectionLayout::normalizeStack($row['stack']),
                'style' => SectionStyle::normalize(is_array($style) ? $style : []),
            ];
        }

        return $sections;
    }

    /**
     * A page's blocks gathered under the sections that hold them, in drawing order.
     *
     * The flat list stays the list: MediaPicture, PageLinks and FormBlocks each resolve a
     * whole page in one query and have no use for its arrangement, so grouping happens
     * once, here, at the moment of drawing — rather than every reader learning a shape it
     * does not need.
     *
     * A block whose section has vanished is dropped rather than drawn loose. That cannot
     * happen after 0026 (Page::update() is the only writer and Sections::prune() only ever
     * removes empty ones); if a later bug makes one, a block missing from the page is a
     * fault somebody reports, where a block drawn with no surface or rhythm looks like a
     * design mistake and gets lived with.
     *
     * BY WHATEVER NAMES A SECTION. The front end joins on the section's id; the editor
     * joins on its key, because a section made in this session has no id yet (D-098). The
     * join is the same either way — one side is the array's key and the other is what a
     * block says it stands in — so it is written once rather than twice.
     *
     * AN EMPTY BAND IS NOT DRAWN on a page, and IS drawn in the editor. To a visitor it is
     * a surface and a rhythm around nothing, and prune() removes it on the next save; to an
     * author it is the band they just added and are about to fill, and a "+ Section" that
     * appeared to do nothing would be the editor lying about what it had done (D-101).
     *
     * @param array<int|string, array{sort?: int, layout: string, stack: string, style: array<string, string|int|null>}> $sections
     * @param list<array<string, mixed>> $blocks each naming its section and column
     * @return list<array{id: int|string, section: array{sort?: int, layout: string, stack: string, style: array<string, string|int|null>}, blocks: list<array<string, mixed>>}>
     */
    public static function group(array $sections, array $blocks, bool $keepEmpty = false): array
    {
        $held = [];
        foreach ($blocks as $block) {
            if (isset($sections[$block['section']])) {
                $held[$block['section']][] = $block;
            }
        }

        $grouped = [];
        foreach ($sections as $id => $section) {
            if (!isset($held[$id]) && !$keepEmpty) {
                continue;
            }
            $grouped[] = ['id' => $id, 'section' => $section, 'blocks' => $held[$id] ?? []];
        }

        return $grouped;
    }

    /**
     * Write a section's style and place, creating it when the block has none yet.
     *
     * The style is resolved against `media` here rather than by the caller, so a background
     * picture that has been deleted since becomes null on save wherever the save came from —
     * the rule D-024 set, kept in one place now that there is one place for it.
     *
     * The arrangement is optional and null means "leave it as it is", because most writers
     * are saying something about the style and nothing about the columns: a block edited in
     * the plain page editor must not quietly collapse the section it sits in back to one
     * column. A new section with nothing said about it is one column that stacks.
     *
     * THE STYLE IS OPTIONAL FOR THE SAME REASON: null leaves it. A section holding a block
     * this installation can no longer draw is saved by a form that never rendered its
     * style, and its stored style is the only record of what it was — so silence about it
     * has to mean "as it was" and not "the defaults", which would quietly repaint it.
     *
     * @param array<string, string|int|null>|null $style
     * @return int the section's id, for the block row to point at
     */
    public static function save(
        Db $db,
        int $pageId,
        ?int $sectionId,
        int $sort,
        ?array $style,
        string $now,
        ?string $layout = null,
        ?string $stack = null,
    ): int {
        // A section id from a form is somebody's input until it is shown to belong to this
        // page; a stale one makes a new section rather than writing over a stranger's.
        $existing = $sectionId === null ? null : $db->one(
            'SELECT id, layout, stack, style_json FROM page_sections WHERE id = ? AND page_id = ?',
            [$sectionId, $pageId],
        );
        if ($existing !== null) {
            $kept = json_decode((string) $existing['style_json'], true);
            $json = self::json(SectionStyle::resolve(
                $db,
                SectionStyle::normalize($style ?? (is_array($kept) ? $kept : [])),
            ));
            $db->query(
                'UPDATE page_sections SET sort = ?, layout = ?, stack = ?, style_json = ?, updated_at = ?
                 WHERE id = ? AND page_id = ?',
                [
                    $sort,
                    SectionLayout::normalize($layout ?? $existing['layout']),
                    SectionLayout::normalizeStack($stack ?? $existing['stack']),
                    $json,
                    $now,
                    $sectionId,
                    $pageId,
                ],
            );

            return (int) $sectionId;
        }

        $db->query(
            'INSERT INTO page_sections (page_id, sort, layout, stack, style_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $pageId,
                $sort,
                SectionLayout::normalize($layout),
                SectionLayout::normalizeStack($stack),
                self::json(SectionStyle::resolve($db, SectionStyle::normalize($style ?? []))),
                $now,
                $now,
            ],
        );

        return (int) $db->lastInsertId();
    }

    /**
     * Delete this page's sections that no longer hold a block.
     *
     * Blocks are removed by Page::update() one at a time, and a section left holding nothing
     * would go on drawing its surface and its rhythm around an empty container. Done as a
     * read and then deletes by id rather than as a DELETE with a subquery over a joined
     * table, which MySQL refuses where SQLite allows it (SPEC §5.0 portability).
     */
    public static function prune(Db $db, int $pageId): void
    {
        $empty = [];
        foreach ($db->all('SELECT id FROM page_sections WHERE page_id = ?', [$pageId]) as $row) {
            $id = (int) $row['id'];
            $holds = $db->one('SELECT COUNT(*) AS n FROM page_blocks WHERE section_id = ?', [$id]);
            if ((int) ($holds['n'] ?? 0) === 0) {
                $empty[] = $id;
            }
        }
        foreach ($empty as $id) {
            $db->query('DELETE FROM page_sections WHERE id = ?', [$id]);
        }
    }

    /**
     * Copy a page's sections onto another page, returning old id => new id.
     *
     * For a translation (Translations::create), which copies a page's blocks verbatim into
     * another locale: without the sections coming too, a Croatian page would be drawn with
     * whatever arrangement its own blocks happened to have.
     *
     * @return array<int, int>
     */
    public static function copy(Db $db, int $fromPageId, int $toPageId, string $now): array
    {
        $map = [];
        foreach ($db->all(
            'SELECT id, sort, layout, stack, style_json FROM page_sections WHERE page_id = ? ORDER BY sort, id',
            [$fromPageId],
        ) as $row) {
            $db->query(
                'INSERT INTO page_sections (page_id, sort, layout, stack, style_json, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $toPageId,
                    (int) $row['sort'],
                    (string) $row['layout'],
                    (string) $row['stack'],
                    (string) $row['style_json'],
                    $now,
                    $now,
                ],
            );
            $map[(int) $row['id']] = (int) $db->lastInsertId();
        }

        return $map;
    }

    /**
     * @param array<string, string|int|null> $style
     */
    private static function json(array $style): string
    {
        return $style === []
            ? '{}'
            : (string) json_encode($style, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
