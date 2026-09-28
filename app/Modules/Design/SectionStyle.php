<?php

namespace App\Modules\Design;

use App\Core\Db;

/**
 * Layer 2: the section style of one block instance, stored in page_blocks.style_json
 * and rendered as class names on its wrapper. The CSS for every class is
 * public/assets/sections.css.
 *
 * Five enumerated keys, and one media reference (PLAN.md D-024). The five are closed
 * sets, which is what stops a section style becoming a free-colour field; `image` is a
 * media id, meaningful only when surface is `image`, and it is not a class — a picture is
 * rendered, not painted by CSS.
 *
 * The alternative was a background field on every block definition, which was rejected:
 * a surface belongs to the section, so every block would have carried the same field for
 * something none of them owns.
 */
final class SectionStyle
{
    /** The closed sets of SPEC §5.4. */
    public const OPTIONS = [
        'surface' => ['plain', 'tinted', 'contrast', 'image', 'gradient'],
        'rhythm' => ['tight', 'normal', 'airy'],
        'width' => ['narrow', 'normal', 'wide', 'full'],
        'align' => ['left', 'center'],
        'divider' => ['none', 'line', 'slant', 'curve'],
    ];

    /**
     * The sixth key. Not in OPTIONS, because OPTIONS is what classes() turns into class
     * names and what the editor renders as selects — a media id is neither.
     */
    public const IMAGE = 'image';

    public const DEFAULTS = [
        'surface' => 'plain',
        'rhythm' => 'normal',
        'width' => 'normal',
        'align' => 'left',
        'divider' => 'none',
        // The sixth key belongs here too: DEFAULTS describes what normalize() returns for
        // a style with nothing usable in it, and a section with no picture is the default
        // state, not a missing one.
        self::IMAGE => null,
    ];

    /**
     * Every enumerated key with a value from its closed set, plus the media reference.
     * Unknown keys are dropped and unknown values fall back to the default.
     *
     * SHAPE ONLY. Whether the id names a picture that still exists is a question for a
     * database, and this is called from eight places that do not all have one — including
     * the demo seeder and the block form. resolve() is the half that asks.
     *
     * @return array<string, string|int|null>
     */
    public static function normalize(mixed $style): array
    {
        $normalized = [];
        foreach (self::OPTIONS as $key => $values) {
            $value = is_array($style) ? ($style[$key] ?? null) : null;
            $normalized[$key] = is_string($value) && in_array($value, $values, true) ? $value : self::DEFAULTS[$key];
        }

        // A media id or nothing. A string of digits is accepted because that is what a
        // form sends; anything else, including 0 and a negative, becomes null.
        $image = is_array($style) ? ($style[self::IMAGE] ?? null) : null;
        $id = is_int($image) ? $image : (is_string($image) && ctype_digit($image) ? (int) $image : 0);
        $normalized[self::IMAGE] = $id > 0 ? $id : null;

        return $normalized;
    }

    /**
     * The same style with an image id that no longer names a picture set to null.
     *
     * Called where a database is at hand: on save, so nothing stored points at a deleted
     * picture. On render the question answers itself — the renderer looks the picture up,
     * finds nothing, and the section falls back to how `contrast` looks, which is exactly
     * what `surface: image` did before pictures existed.
     *
     * @param array<string, string|int|null> $style normalized
     * @return array<string, string|int|null>
     */
    public static function resolve(Db $db, array $style): array
    {
        $id = $style[self::IMAGE] ?? null;
        if (!is_int($id) || $id <= 0) {
            $style[self::IMAGE] = null;

            return $style;
        }
        if ($db->one('SELECT id FROM media WHERE id = ?', [$id]) === null) {
            $style[self::IMAGE] = null;
        }

        return $style;
    }

    /**
     * The class names for the enumerated keys only. The media reference is not among
     * them: a picture is rendered into the section, not expressed as a class.
     *
     * @param array<string, string|int|null> $style normalized
     * @return list<string> e.g. surface-tinted, rhythm-airy
     */
    public static function classes(array $style): array
    {
        $classes = [];
        foreach (self::OPTIONS as $key => $values) {
            $value = $style[$key] ?? self::DEFAULTS[$key];
            $classes[] = $key . '-' . (is_string($value) ? $value : self::DEFAULTS[$key]);
        }

        return $classes;
    }
}
