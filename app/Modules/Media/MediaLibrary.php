<?php

namespace App\Modules\Media;

use App\Core\Blocks;
use App\Core\Db;
use App\Core\Settings;
use App\Modules\Design\SectionStyle;
use App\Modules\Settings\SiteChrome;

/**
 * The picture library: what there is, what uses it, and what happens when one goes.
 *
 * FINDING WHAT USES A PICTURE is the interesting part. A media id is a plain integer
 * inside page_blocks.content_json, and neither MySQL nor SQLite can be relied on for JSON
 * functions — SQLite's are a compile-time option, and the SQL would differ anyway.
 *
 * So: narrow with LIKE, confirm in PHP. The narrowing is portable and uses the index-free
 * scan once; the confirmation is exact. Both halves are needed, because LIKE '%"image":7%'
 * also matches id 70 — measured, 2 rows on both engines — and deleting a picture because
 * a different picture's id happens to start with the same digits would be unforgivable.
 */
final class MediaLibrary
{
    public function __construct(
        private readonly Db $db,
        private readonly Blocks $registry,
        private readonly string $storagePath,
        private readonly string $publicPath,
    ) {
    }

    /**
     * Pictures, newest first, optionally filtered by filename.
     *
     * The search is on the generated filename rather than the original: the original is
     * whatever the camera called it, and "DSC_0042" is not what anyone types.
     *
     * $limit null is every row: the library's own list pages what it shows (the owner,
     * 2026-09-27), and the picker keeps its newest 200 with a search for the rest.
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $search = '', ?int $limit = 200, ?string $kind = null): array
    {
        // Pictures, files (D-126), or both. Bound, never spliced: it is a value from a query
        // string by the time it gets here, however the caller narrowed it.
        $where = [];
        $params = [];
        if ($kind !== null) {
            $where[] = 'kind = ?';
            $params[] = $kind;
        }
        $search = trim($search);
        if ($search !== '') {
            $where[] = '(filename LIKE ? OR original_name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        return $this->rows(
            'SELECT * FROM media' . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where)) . ' ORDER BY id DESC' . ($limit === null ? '' : ' LIMIT ' . $limit),
            $params,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM media WHERE id = ?', [$id]);
    }

    /**
     * The pages using this picture, as id => title.
     *
     * Every field the block registry declares as `media` is checked, so a block type
     * added later is covered without editing this. Written as a loop over SOURCES so
     * that a second place a picture can be referenced — a section's surface, once that
     * stores an id — is one more entry rather than a rewrite.
     *
     * @return array<int, string>
     */
    public function usedBy(int $mediaId): array
    {
        $used = [];
        foreach ($this->candidates($mediaId) as $row) {
            // A picture anywhere in a block's content: its own fields (hero.image) and the
            // items of a repeater (a Columns block's columns). MediaReference::idsIn() is the
            // one traversal of that shape; this checked only the top level until a column's
            // picture could be deleted from under its page (D-052). The exact comparison
            // the LIKE could not make: 7 is not 70.
            $content = json_decode((string) $row['content_json'], true);
            if (is_array($content) && in_array($mediaId, MediaReference::idsIn($this->registry, (string) $row['block_type'], $content, MediaReference::REFERENCES), true)) {
                $used[(int) $row['page_id']] = (string) $row['title'];
            }

            // A picture chosen as the section's own surface (D-024). Same narrowing, same
            // confirmation: style_json carries "image":7 exactly as content_json does, and
            // the LIKE cannot tell it from "image":70 either.
            $style = json_decode((string) ($row['style_json'] ?? ''), true);
            if (is_array($style) && isset($style[SectionStyle::IMAGE])
                && is_int($style[SectionStyle::IMAGE]) && $style[SectionStyle::IMAGE] === $mediaId) {
                $used[(int) $row['page_id']] = (string) $row['title'];
            }
        }

        return $used;
    }

    /**
     * How many pages use each picture, and which the site itself uses as its logo, favicon
     * or sharing picture — for the Media table's "Used on" and its Unused filter (D-052).
     *
     * One pass over every block, where usedBy() asks about one picture: a table of two
     * hundred pictures asking one at a time would be two hundred scans. The same traversal
     * as usedBy(), so the list and the refusal to delete can never disagree.
     *
     * @return array<int, array{pages: int, site: bool}> media id => its use; absent is unused
     */
    public function usage(): array
    {
        $pages = [];
        // The style is the SECTION's since D-095, joined on so this stays one traversal.
        foreach ($this->rows(
            'SELECT b.page_id, b.block_type, b.content_json, s.style_json
             FROM page_blocks b LEFT JOIN page_sections s ON s.id = b.section_id',
        ) as $row) {
            $content = json_decode((string) $row['content_json'], true);
            $ids = is_array($content) ? MediaReference::idsIn($this->registry, (string) $row['block_type'], $content, MediaReference::REFERENCES) : [];
            $style = json_decode((string) ($row['style_json'] ?? ''), true);
            if (is_array($style) && is_int($style[SectionStyle::IMAGE] ?? null)) {
                $ids[] = $style[SectionStyle::IMAGE];
            }
            foreach ($ids as $id) {
                $pages[$id][(int) $row['page_id']] = true;
            }
        }

        $usage = [];
        foreach ($pages as $id => $on) {
            $usage[$id] = ['pages' => count($on), 'site' => false];
        }
        $site = [SiteChrome::logo($this->db), Settings::mediaId($this->db, 'site_favicon'), Settings::mediaId($this->db, 'site_share_image')];
        foreach (array_filter($site) as $id) {
            $usage[$id] = ['pages' => $usage[$id]['pages'] ?? 0, 'site' => true];
        }

        return $usage;
    }

    /**
     * Removes a picture, its variants and its original — unless a page still uses it.
     *
     * @return array{deleted: bool, used_by: array<int, string>}
     */
    public function delete(int $mediaId): array
    {
        $media = $this->find($mediaId);
        if ($media === null) {
            return ['deleted' => false, 'used_by' => []];
        }

        $used = $this->usedBy($mediaId);
        if ($used !== []) {
            return ['deleted' => false, 'used_by' => $used];
        }

        // Files first, then the row. The other order would leave orphaned files with
        // nothing left to say which picture they belonged to; this way a failure halfway
        // leaves a row whose variants can be regenerated.
        $this->forgetVariants($media);
        $original = $this->storagePath . '/' . (string) $media['path'];
        if (is_file($original)) {
            @unlink($original);
        }

        // media_meta goes with it through the foreign key's ON DELETE CASCADE.
        $this->db->query('DELETE FROM media WHERE id = ?', [$mediaId]);

        return ['deleted' => true, 'used_by' => []];
    }

    /**
     * Removes what was generated from a picture, leaving the row and the original.
     *
     * Takes the ROW rather than an id, because both callers already hold one and one of
     * them needs the row as it stood: replacing a picture's bytes clears variants_json,
     * and after that nothing records which files were ever made.
     *
     * @param array<string, mixed> $media
     */
    public function forgetVariants(array $media): void
    {
        $mediaId = (int) $media['id'];
        $filename = (string) $media['filename'];

        foreach (MediaVariants::of($media) as $preset => $variant) {
            foreach ($variant['formats'] as $format) {
                $file = $this->publicPath . '/' . MediaPresets::file($preset, $mediaId, $filename, $format);
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        // Then everything else wearing this picture's name. variants_json lists what was
        // SUCCESSFULLY written, so an encode that failed part-way leaves a file no record
        // mentions, and deleting the picture left it behind for ever with nothing to say
        // whose it was. The id makes the pattern unambiguous: 12-photo.* cannot match
        // 123-photo.*.
        foreach (MediaPresets::names() as $preset) {
            $pattern = $this->publicPath . '/m/' . $preset . '/' . $mediaId . '-' . $filename . '.*';
            foreach (glob($pattern, GLOB_NOSORT) ?: [] as $orphan) {
                if (is_file($orphan)) {
                    @unlink($orphan);
                }
            }
        }
    }

    /* What a picture MEANS — its alt text and caption, per language — is MediaMeta. */

    /**
     * Where the focal point sits, as percentages. Cropped presets keep it in frame, so
     * moving it changes every crop the next time they are generated.
     */
    public function setFocalPoint(int $mediaId, int $x, int $y): void
    {
        $this->db->query(
            'UPDATE media SET focal_x = ?, focal_y = ?, variants_json = NULL, status = ? WHERE id = ?',
            [max(0, min(100, $x)), max(0, min(100, $y)), 'incomplete', $mediaId],
        );
    }

    /**
     * Moves the point every crop keeps in frame, and nothing else (PLAN.md D-121): the crops
     * are made again by MediaRemake::now(), which replaces each file only once its successor
     * is whole. setFocalPoint() above empties the variants as well, which is right for a
     * picture that has none yet — the crop dialog's new one — and wrong for one on show.
     */
    public function moveFocalPoint(int $mediaId, int $x, int $y): void
    {
        $this->db->query(
            'UPDATE media SET focal_x = ?, focal_y = ? WHERE id = ?',
            [max(0, min(100, $x)), max(0, min(100, $y)), $mediaId],
        );
    }

    /**
     * Blocks that MIGHT hold this id, narrowed by the database and confirmed by the
     * caller. The page title travels with them so a refusal can name pages.
     *
     * @return list<array<string, mixed>>
     */
    private function candidates(int $mediaId): array
    {
        // One pattern per media field name, because an id is stored as "image":7 — after
        // a COLON, not after a quote. A pattern of '%"7%' matches only text that happens
        // to contain a quote then a 7, which is every heading beginning "7 reasons" and
        // no media reference at all: it found nothing real and over-fetched prose.
        //
        // The trailing digits are still ambiguous — "image":7 matches id 7 and id 70 —
        // which is what the PHP decode in usedBy() settles. Whether this narrows the scan
        // usefully on a large site is untested: on a small fixture it fetches the same
        // number of rows as the broken pattern did, so the reason to prefer it is that it
        // matches media references at all, not a measured saving.
        //
        // Every media field's name, a repeater item's included: "image":7 inside a column
        // is written exactly as it is at the top level.
        $names = [];
        foreach ($this->registry->types() as $type) {
            foreach ($this->registry->get($type)['fields'] as $name => $field) {
                if (in_array($field['type'] ?? '', MediaReference::REFERENCES, true)) {
                    $names[(string) $name] = true;
                }
                foreach (($field['type'] ?? '') === 'repeater' ? $field['fields'] : [] as $itemName => $itemField) {
                    if (in_array($itemField['type'] ?? '', MediaReference::REFERENCES, true)) {
                        $names[(string) $itemName] = true;
                    }
                }
            }
        }
        if ($names === []) {
            return [];
        }

        $conditions = [];
        $params = [];
        foreach (array_keys($names) as $field) {
            $conditions[] = 'b.content_json LIKE ?';
            $params[] = '%"' . $field . '":' . $mediaId . '%';
        }

        // The section surface is one more source, which is why this was written as a
        // list of conditions rather than a single pattern. Since D-095 it is the SECTION's
        // style_json, and getting this wrong is not a display bug: usedBy() is what refuses
        // to delete a picture, so a missed source would unlink one still painted behind a
        // section.
        $conditions[] = 's.style_json LIKE ?';
        $params[] = '%"' . SectionStyle::IMAGE . '":' . $mediaId . '%';

        return $this->rows(
            'SELECT b.page_id, b.block_type, b.content_json, s.style_json, p.title
             FROM page_blocks b
             JOIN pages p ON p.id = b.page_id
             LEFT JOIN page_sections s ON s.id = b.section_id
             WHERE ' . implode(' OR ', $conditions),
            $params,
        );
    }

    /**
     * @param array<int, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        $rows = [];
        foreach ($this->db->all($sql, $params) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }
}
