<?php

namespace App\Modules\Media;

use App\Core\Blocks;
use App\Core\Db;

/**
 * Which block fields hold a picture, and what becomes of an id that names none.
 *
 * A media id is a plain integer inside page_blocks.content_json, and nothing in the
 * database enforces that it points anywhere — a foreign key would have to reach inside
 * JSON. So the rule is the one D-024 set for a section's background picture, applied to
 * block content as well: AN ID THAT NAMES NO PICTURE BECOMES NULL ON SAVE.
 *
 * The failure it prevents is not hypothetical. The demo shipped image => 1..6 for
 * pictures that were never uploaded, so on a fresh install the first photograph put into
 * the library was silently adopted by a demo page that never meant it — and could then
 * not be deleted, because a page "used" it.
 *
 * Deliberately NOT part of Blocks::normalize(), which is pure and called from places with
 * no database: the same split as SectionStyle::normalize() and ::resolve(). On RENDER
 * nothing is resolved — the renderer looks the picture up, finds nothing and shows the
 * placeholder, which is the behaviour that already existed.
 */
final class MediaReference
{
    /** The field types that point into the library: a picture, and a file (D-126). */
    public const REFERENCES = ['media', 'file'];

    /**
     * Every media id inside one block's content, wherever it lives (PLAN.md O-11).
     *
     * ONE TRAVERSAL, HERE. Both halves of the media contract walked block content
     * separately and both walked only the top level: resolve() below, which nulls an id
     * naming no picture on save, and MediaPicture::forBlocks(), which resolves pictures for
     * rendering. A media field inside a repeater item was invisible to both — it would have
     * worked in the editor and vanished from the page, which is the first bug an owner
     * would have met. Two traversals of one shape is how one gets fixed and the other does
     * not, so forBlocks() now asks this.
     *
     * WHICH KINDS OF FIELD: `media` (a picture) by default, which is what drawing pictures
     * asks for; `file` (D-126) as well wherever the question is whether the library item is
     * used at all — so a file on a Downloads block is refused deletion exactly as a picture
     * on a page is. `REFERENCES` names both.
     *
     * @param array<string, mixed> $content normalized content
     * @param list<string> $types
     * @return list<int>
     */
    public static function idsIn(Blocks $registry, string $type, array $content, array $types = ['media']): array
    {
        if (!$registry->has($type)) {
            return [];
        }

        $ids = [];
        foreach ($registry->get($type)['fields'] as $name => $field) {
            $value = $content[$name] ?? null;
            if (in_array($field['type'] ?? '', $types, true)) {
                if (is_int($value) && $value > 0) {
                    $ids[] = $value;
                }
                continue;
            }
            if (($field['type'] ?? '') !== 'repeater' || !is_array($value)) {
                continue;
            }
            foreach ($field['fields'] as $itemName => $itemField) {
                if (!in_array($itemField['type'] ?? '', $types, true)) {
                    continue;
                }
                foreach ($value as $item) {
                    $id = is_array($item) ? ($item[$itemName] ?? null) : null;
                    if (is_int($id) && $id > 0) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * The data attributes media-picker.js reads off a <select data-media-field>.
     *
     * Extracted from the block editor's view when site settings became a second screen
     * with pickers on it. Six attributes copied into a second template is how one of them
     * quietly stops matching the script and that screen's picker loses its labels.
     *
     * Presentation in a data class, which is not where it belongs — but the alternatives
     * were a one-method class in Support or a partial that returns a string, and the
     * vocabulary already lives beside choices(), which is the picker's other server half.
     * The keys stay under pages.field.* because that is where they were written; renaming
     * them would touch two templates and change no behaviour.
     */
    public static function pickerAttributes(): string
    {
        return ' data-picker-url="' . e(\App\Support\Url::admin('media')) . '"'
            . ' data-text-none="' . e(t('pages.field.media_none')) . '"'
            . ' data-text-search="' . e(t('media.search')) . '"'
            . ' data-text-failed="' . e(t('media.pick_failed')) . '"'
            . ' data-text-choose="' . e(t('media.pick_choose')) . '"'
            . ' data-text-change="' . e(t('media.pick_change')) . '"';
    }

    /**
     * The pictures a field may choose from, newest first: id, library name, and the
     * thumbnail to show for the one currently chosen.
     *
     * The page editors need this to offer a choice rather than ask for a number, and they
     * are the wrong place to know how pictures are stored — so it lives here, beside the
     * rule about what a reference means.
     *
     * The thumbnail URL is built HERE and never in the browser. Media URLs use named
     * presets only (SPEC §6); a picker that assembled one from an id and a filename would
     * be a second implementation of that contract, in JavaScript, free to drift from it.
     * A picture whose thumbnail has not been generated yet returns null and the picker
     * shows its name alone.
     *
     * @return list<array{id: int, name: string, thumb: string|null, whole: string|null}>
     */
    public static function choices(Db $db, int $limit = 200): array
    {
        $choices = [];
        // Pictures only: a document in the library (D-126) is not something a picture field
        // can show.
        foreach ($db->all("SELECT id, filename, hash, revision, variants_json FROM media WHERE kind = 'picture' ORDER BY id DESC LIMIT " . $limit) as $row) {
            $choices[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['filename'],
                'thumb' => MediaVariants::url($row, 'thumb'),
                // The whole picture, uncropped: a logo's preview must keep its shape (D-038).
                'whole' => MediaVariants::url($row, 'full'),
            ];
        }

        return $choices;
    }

    /**
     * The same content with every media id that names no picture set to null.
     *
     * @param array<string, mixed> $content normalized
     * @return array<string, mixed>
     */
    public static function resolve(Db $db, Blocks $registry, string $type, array $content): array
    {
        if (!$registry->has($type)) {
            return $content;
        }

        // One query for every id the block carries, wherever it lives, rather than one per
        // field — and the same traversal idsIn() uses, so the rule reaches inside a
        // repeater's items exactly as far as the renderer does.
        // What each id is — a picture or a file (D-126) — so a picture field keeps only a
        // picture and a file field only a file: an id of the wrong kind names nothing that
        // field can show, the same as an id nobody has.
        $known = [];
        foreach (self::idsIn($registry, $type, $content, self::REFERENCES) as $id) {
            $known[$id] = '';
        }
        if ($known !== []) {
            $placeholders = implode(', ', array_fill(0, count($known), '?'));
            foreach ($db->all("SELECT id, kind FROM media WHERE id IN ({$placeholders})", array_keys($known)) as $row) {
                $known[(int) $row['id']] = (string) $row['kind'];
            }
        }
        $kindFor = static fn (string $fieldType): string => $fieldType === 'file' ? 'file' : 'picture';
        $keep = static fn (mixed $id, string $fieldType): ?int => is_int($id) && $id > 0 && ($known[$id] ?? '') === $kindFor($fieldType) ? $id : null;

        foreach ($registry->get($type)['fields'] as $name => $field) {
            // Only fields the content actually carries: this never adds a key that
            // normalize() did not put there.
            if (!array_key_exists($name, $content)) {
                continue;
            }
            if (in_array($field['type'] ?? '', self::REFERENCES, true)) {
                $content[$name] = $keep($content[$name], (string) $field['type']);
                continue;
            }
            if (($field['type'] ?? '') !== 'repeater' || !is_array($content[$name])) {
                continue;
            }

            // A picture chosen inside an item and deleted from the library afterwards. This
            // walked the top level only, as did the renderer, so such an id survived the
            // save and then drew nothing on the page (O-11).
            $items = [];
            foreach ($content[$name] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                foreach ($field['fields'] as $itemName => $itemField) {
                    if (in_array($itemField['type'] ?? '', self::REFERENCES, true) && array_key_exists($itemName, $item)) {
                        $item[$itemName] = $keep($item[$itemName], (string) $itemField['type']);
                    }
                }
                $items[] = $item;
            }
            $content[$name] = $items;
        }

        return $content;
    }
}
