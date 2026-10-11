<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\SectionStyle;
use PDOException;

/**
 * A page's DRAFT (PLAN.md D-163, D-173, README 2.1): the whole page as a document, saved as
 * the owner works, while what visitors see stays in the published tables until Publish.
 *
 * The editors read current(): the draft when there is one, else the page as stored — so an
 * editor never has to know which it is drawing, and the first save of a page that had none
 * makes one. Publishing and discarding are PagePublish's: they touch the published page.
 *
 * VERSIONED. A save names the version it was made from; one made from a version the row has
 * moved past is refused, so a second tab cannot quietly overwrite the first. Version 0 is
 * "there was no draft".
 *
 * @phpstan-import-type Document from PageDocument
 */
final class PageDraft
{
    /**
     * The draft, or null when the page has none (or its row cannot be read as a document).
     *
     * @return array{document: Document, version: int, updated_at: string}|null
     */
    public static function find(Db $db, Blocks $registry, int $pageId): ?array
    {
        $row = $db->one('SELECT draft_json, version, updated_at FROM page_drafts WHERE page_id = ?', [$pageId]);
        if ($row === null) {
            return null;
        }
        $document = PageDocument::decode($registry, (string) $row['draft_json']);

        return $document === null ? null : [
            'document' => $document,
            'version' => (int) $row['version'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * What an editor works on: the draft when there is one, else the page as stored.
     *
     * @return array{document: Document, version: int, drafted: bool}|null null when there is no page
     */
    public static function current(Db $db, Blocks $registry, int $pageId): ?array
    {
        $draft = self::find($db, $registry, $pageId);
        if ($draft !== null) {
            return ['document' => $draft['document'], 'version' => $draft['version'], 'drafted' => true];
        }
        $stored = PageDocument::stored($db, $registry, $pageId);

        return $stored === null ? null : ['document' => $stored, 'version' => 0, 'drafted' => false];
    }

    public static function exists(Db $db, int $pageId): bool
    {
        return $db->one('SELECT page_id FROM page_drafts WHERE page_id = ?', [$pageId]) !== null;
    }

    /**
     * Saves the document as the page's draft, made from version $from.
     *
     * One statement decides, so two saves cannot both pass a check and then both write: the
     * update names the version it expects and changes nothing when the row has moved on, and
     * a first draft is an insert the primary key refuses a second time.
     *
     * @param Document $document
     * @return int|null the draft's new version, or null when $from is not the current one
     */
    public static function save(Db $db, int $pageId, array $document, int $from, ?int $by = null): ?int
    {
        $json = PageDocument::encode($document);
        if ($json === null) {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($from === 0) {
            try {
                $db->query(
                    'INSERT INTO page_drafts (page_id, draft_json, version, updated_at, updated_by) VALUES (?, ?, 1, ?, ?)',
                    [$pageId, $json, $now, $by],
                );
            } catch (PDOException) {
                // Another tab made the first draft between this one loading and saving.
                return null;
            }

            return 1;
        }
        $changed = $db->query(
            'UPDATE page_drafts SET draft_json = ?, version = version + 1, updated_at = ?, updated_by = ? WHERE page_id = ? AND version = ?',
            [$json, $now, $by, $pageId, $from],
        )->rowCount();

        return $changed === 1 ? $from + 1 : null;
    }

    /** Deletes the draft: the editors are back to the page as stored. */
    public static function discard(Db $db, int $pageId): void
    {
        $db->query('DELETE FROM page_drafts WHERE page_id = ?', [$pageId]);
    }

    /**
     * Every draft handed back to a character, as Composition::apply() hands back the published
     * pages (D-163 point 5): each section's composed keys to ''. A block's layout and options
     * stay (D-191). Never content. Each draft's version moves on, so a tab still open on it is
     * told it changed.
     */
    public static function handBack(Db $db, Blocks $registry): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($db->all('SELECT page_id, draft_json, version FROM page_drafts') as $row) {
            $document = PageDocument::decode($registry, (string) $row['draft_json']);
            if ($document === null) {
                continue;
            }
            foreach ($document['sections'] as $at => $section) {
                $document['sections'][$at]['style'] = SectionStyle::reset($section['style']);
            }
            $json = PageDocument::encode($document);
            if ($json !== null) {
                $db->query(
                    'UPDATE page_drafts SET draft_json = ?, version = version + 1, updated_at = ? WHERE page_id = ? AND version = ?',
                    [$json, $now, (int) $row['page_id'], (int) $row['version']],
                );
            }
        }
    }

    /**
     * What the editors' bar says about the page (README 2.1): `published` with no draft,
     * `changes` when a published page has a draft, `draft` when it is not published.
     *
     * @param array<string, mixed> $page a pages row
     */
    public static function state(Db $db, array $page): string
    {
        if (($page['status'] ?? '') !== 'published') {
            return 'draft';
        }

        return self::exists($db, (int) $page['id']) ? 'changes' : 'published';
    }
}
