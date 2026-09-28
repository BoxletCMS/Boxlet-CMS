<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;
use JsonException;

/**
 * What a page was before the last few saves (PLAN.md D-088).
 *
 * Undo covers the editing session and dies with the tab. This covers the saves: a heading
 * rewritten last Tuesday, a paragraph deleted, a block removed and saved. What it changes
 * is not what the editor can do but what the owner dares do with it.
 *
 * A REVISION IS AN EDITING EVENT, NOT A ROW REWRITE, which is why recording one is the
 * controller's job and not Page::update()'s. The demo seed calls update() too, and a fresh
 * install does not want four pages of history nobody made.
 *
 * WHAT IT HOLDS is the page as the editor reads it — its settings and every block in the
 * shape Page::editable() returns — so restoring is an ordinary save. Not a shortcut past
 * validation, media resolution and the sitemap: a restore that skipped those would be the
 * one code path nobody exercises until the day it matters.
 */
final class PageRevision
{
    /**
     * How many a page keeps. Enough to cover the mistake this exists for; a limit at all
     * because a site's whole history on hosting sold by the gigabyte is not a kindness.
     * Twenty steps of undo follows the same reasoning at the other end of the scale.
     */
    public const KEEP = 5;

    /**
     * Record the page AS IT IS NOW, before whatever is about to be saved over it.
     *
     * Read from the database rather than from what was submitted: with D-081 a save may
     * carry only the blocks that changed, and "what the page was" has to be true of the
     * whole page whatever arrived in the request.
     */
    public static function record(Db $db, Blocks $registry, int $pageId): void
    {
        $page = Page::find($db, $pageId);
        if ($page === null) {
            return;
        }

        $data = [
            'title' => (string) $page['title'],
            'slug' => (string) $page['slug'],
            'parent_id' => $page['parent_id'] === null ? null : (int) $page['parent_id'],
            'status' => (string) $page['status'],
            'seo_json' => (string) ($page['seo_json'] ?? '{}'),
            'blocks' => Page::editable($db, $registry, $pageId),
            // AND HOW THEY STOOD (PLAN.md D-098). Without this, restoring a page that had
            // two columns when it was recorded would put its blocks back into whatever
            // arrangement the page has NOW — the content of last Tuesday in this week's
            // bands, which is neither one page nor the other. A revision written before
            // this has no sections and says so by their absence.
            'sections' => Page::editableSections($db, $pageId),
        ];

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // A page that cannot be written down is not a reason to refuse the save the
            // owner asked for. They lose the safety net for this one save, not their work.
            return;
        }

        $db->query(
            'INSERT INTO page_revisions (page_id, data_json, created_at) VALUES (?, ?, ?)',
            [$pageId, $json, gmdate('Y-m-d H:i:s')],
        );
        self::prune($db, $pageId);
    }

    /**
     * This page's revisions, newest first: id and when, never the data. A list screen does
     * not need five whole pages of JSON to draw five lines.
     *
     * @return list<array{id: int, created_at: string}>
     */
    public static function all(Db $db, int $pageId): array
    {
        $rows = [];
        foreach ($db->all(
            'SELECT id, created_at FROM page_revisions WHERE page_id = ? ORDER BY created_at DESC, id DESC',
            [$pageId],
        ) as $row) {
            $rows[] = ['id' => (int) $row['id'], 'created_at' => (string) $row['created_at']];
        }

        return $rows;
    }

    /**
     * One revision's page, ready to be saved, or null when the id is not this page's.
     *
     * THE PAGE ID IS CHECKED HERE, not by the caller: this is reached from a request, and
     * "restore revision 41 into page 3" must not be able to pour another page's blocks into
     * this one. The shape is checked too — a row written by an older version of this file,
     * or edited by hand, is refused rather than half-applied.
     *
     * @return array{title: string, slug: string, parent_id: int|null, status: string, seo_json: string, blocks: list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section: string, column: int}>, sections: list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null}|null
     */
    public static function find(Db $db, Blocks $registry, int $pageId, int $revisionId): ?array
    {
        $row = $db->one(
            'SELECT data_json FROM page_revisions WHERE id = ? AND page_id = ?',
            [$revisionId, $pageId],
        );
        if ($row === null) {
            return null;
        }

        try {
            $data = json_decode((string) $row['data_json'], true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($data) || !is_array($data['blocks'] ?? null)) {
            return null;
        }

        /*
         * THE BANDS, when the revision has them.
         *
         * A revision recorded before D-098 has none, and null says "leave the arrangement
         * as it is" all the way down to Sections::save() — which is the only honest answer:
         * that revision does not know what the bands were, and guessing "one column each"
         * would flatten a page whose columns were never what the restore was about.
         */
        $sections = null;
        if (is_array($data['sections'] ?? null)) {
            $sections = [];
            foreach ($data['sections'] as $section) {
                if (!is_array($section) || !is_string($section['key'] ?? null)) {
                    continue;
                }
                $sections[] = [
                    'key' => $section['key'],
                    // Kept for the same reason a block's is: a band that still exists is
                    // written to rather than replaced, so its translations and its
                    // background picture stay attached to it.
                    'id' => isset($section['id']) && is_int($section['id']) ? $section['id'] : null,
                    'layout' => SectionLayout::normalize($section['layout'] ?? null),
                    'stack' => SectionLayout::normalizeStack($section['stack'] ?? null),
                    'style' => \App\Modules\Design\SectionStyle::normalize($section['style'] ?? null),
                ];
            }
        }

        $blocks = [];
        $ordinal = 0;
        foreach ($data['blocks'] as $block) {
            if (!is_array($block) || !is_string($block['type'] ?? null) || !$registry->has($block['type'])) {
                // A block whose type has gone since is left out rather than restored as a
                // hole: normalize() cannot give it a shape and render() cannot draw it.
                continue;
            }
            $id = isset($block['id']) && is_int($block['id']) ? $block['id'] : null;
            $blocks[] = [
                // Derived here rather than read from the row: a key is what the editor
                // calls a block for one visit (D-094), not something a revision records.
                'key' => BlockForm::key($id, $ordinal++),
                // The id is kept so a block that still exists is UPDATED rather than
                // duplicated; one that has since been removed has no row to match and
                // Page::update() inserts it, which is exactly what restoring it means.
                'id' => $id,
                'type' => $block['type'],
                'content' => $registry->normalize($block['type'], is_array($block['content'] ?? null) ? $block['content'] : []),
                'style' => \App\Modules\Design\SectionStyle::normalize($block['style'] ?? null),
                'layout' => $registry->layout($block['type'], $block['layout'] ?? null),
                // Which band it stood in and which of its columns. A revision from before
                // D-098 carries neither, and a block with no band named is given one of its
                // own on the way in — the shape every page had when that revision was made.
                'section' => is_string($block['section'] ?? null) ? $block['section'] : SectionForm::key(null, $ordinal),
                'column' => isset($block['column']) && is_int($block['column']) ? $block['column'] : 0,
            ];
        }

        return [
            'title' => is_string($data['title'] ?? null) ? $data['title'] : '',
            'slug' => is_string($data['slug'] ?? null) ? $data['slug'] : '',
            'parent_id' => isset($data['parent_id']) && is_int($data['parent_id']) ? $data['parent_id'] : null,
            'status' => ($data['status'] ?? '') === 'published' ? 'published' : 'draft',
            'seo_json' => is_string($data['seo_json'] ?? null) ? $data['seo_json'] : '{}',
            'blocks' => $blocks,
            'sections' => $sections,
        ];
    }

    /**
     * Keep the newest KEEP, delete the rest.
     *
     * Two statements rather than a DELETE with a subquery over the same table, which MySQL
     * refuses outright (error 1093) while SQLite allows it — the portability rule in
     * SPEC §5.0 is easiest to keep by not writing the clever version at all.
     */
    private static function prune(Db $db, int $pageId): void
    {
        $keep = [];
        foreach ($db->all(
            'SELECT id FROM page_revisions WHERE page_id = ? ORDER BY created_at DESC, id DESC',
            [$pageId],
        ) as $index => $row) {
            if ($index >= self::KEEP) {
                $keep[] = (int) $row['id'];
            }
        }
        foreach ($keep as $id) {
            $db->query('DELETE FROM page_revisions WHERE id = ?', [$id]);
        }
    }
}
