<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;

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
 * shape PageBlocks::editable() returns — so restoring is an ordinary save. Not a shortcut past
 * validation, media resolution and the sitemap: a restore that skipped those would be the
 * one code path nobody exercises until the day it matters.
 *
 * @phpstan-import-type Document from PageDocument
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
        // The page's settings, its blocks AND HOW THEY STOOD (PLAN.md D-098): without the
        // bands, restoring a page that had two columns when it was recorded would put its
        // blocks into whatever arrangement the page has NOW. One document, the shape a draft
        // has (D-173), so a revision restores into the draft as it is.
        $document = PageDocument::stored($db, $registry, $pageId);
        // A page that cannot be written down is not a reason to refuse the save the owner
        // asked for. They lose the safety net for this one save, not their work.
        $json = $document === null ? null : PageDocument::encode($document);
        if ($json === null) {
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
     * @return Document|null
     */
    public static function find(Db $db, Blocks $registry, int $pageId, int $revisionId): ?array
    {
        $row = $db->one(
            'SELECT data_json FROM page_revisions WHERE id = ? AND page_id = ?',
            [$revisionId, $pageId],
        );

        // Read as any document is (PageDocument::read): rebuilt against the registry, and a
        // row of another shape — one written before D-098, or edited by hand — refused
        // rather than half-applied.
        return $row === null ? null : PageDocument::decode($registry, (string) $row['data_json']);
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
