<?php

namespace App\Modules\Design;

use App\Core\Db;

/**
 * Layer 2: the section style of one section, stored in page_sections.style_json and
 * rendered as class names and two attributes on its wrapper. The CSS for every class is
 * public/assets/sections.css.
 *
 * WHAT IS STORED IS THE OWNER'S, AND ONLY THAT (PLAN.md D-165). Every key of the look —
 * surface, spacing, height, width, alignment, edge, animation — is '' until the owner sets
 * it, and '' means "as the character composes this section". A site that changes character
 * re-dresses every section nobody touched, and keeps every one somebody did, without a
 * question being asked; resetting a section is setting its keys back to ''. The rule the
 * global decisions follow (D-164), applied one layer down.
 *
 * The rest is not the character's to have an opinion on, and so has no '' meaning beyond
 * "none": the background picture (a media id, D-024), the section's name in the editor, its
 * anchor, and whether it is hidden on a kind of screen.
 *
 * CLOSED SETS AND STEPPED NUMBERS, never free values. The spacing and the height are numbers
 * now rather than three named rhythms, but each lands on a step, so every value a section
 * can hold has a class in sections.css — the public pages keep their rule of no style
 * attribute, and a number cannot become a free-length field.
 */
final class SectionStyle
{
    /** The closed sets: each a class `{key}-{value}`, except animation (an attribute). */
    public const OPTIONS = [
        'surface' => ['plain', 'tinted', 'contrast', 'image', 'gradient'],
        'width' => ['narrow', 'normal', 'wide', 'full'],
        'align' => ['left', 'center'],
        'divider' => ['none', 'line', 'slant', 'curve'],
        // Where the content sits in a band taller than it: only meaningful with a height.
        'v_align' => ['top', 'center', 'bottom'],
        'animation' => ['none', 'fade', 'up', 'zoom'],
    ];

    /**
     * The stepped numbers. Padding in CSS pixels, drawn as rem; the height in percent of the
     * window, 0 being "as tall as its content".
     *
     * @var array<string, array{min: int, max: int, step: int}>
     */
    public const NUMBERS = [
        'pad_top' => ['min' => 0, 'max' => 200, 'step' => 4],
        'pad_bottom' => ['min' => 0, 'max' => 200, 'step' => 4],
        'min_height' => ['min' => 0, 'max' => 100, 'step' => 5],
    ];

    /** Hidden on a kind of screen: 'yes' or ''. The three widths sections.css draws them at. */
    public const HIDDEN = ['hide_desktop', 'hide_tablet', 'hide_mobile'];

    /**
     * The background picture's media id. Not in OPTIONS: a picture is rendered into the
     * section, not painted by a class.
     */
    public const IMAGE = 'image';

    /** The editor's own label for the section: never drawn on the page. */
    public const NAME = 'name';

    /** The section's id on the page, for links to `page#anchor`. */
    public const ANCHOR = 'anchor';

    public const NAME_LENGTH = 60;

    /**
     * An anchor is a slug, and never one of the ids the page already uses: the header's menu
     * (`site-nav`, `site-nav-1`…) and a form's own (`form-3`). A section that took one would
     * break the menu button or send a form's answer to the wrong place.
     */
    private const ANCHOR_PATTERN = '~^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$~';
    public const ANCHOR_LENGTH = 64;
    private const RESERVED = ['site-', 'form-'];

    /**
     * What a section looks like when neither its owner nor its character says otherwise.
     * Padding '' is the design's section gap (`--space-section`), which is the character's
     * answer one layer up.
     */
    public const DEFAULTS = [
        'surface' => 'plain',
        'pad_top' => '',
        'pad_bottom' => '',
        'min_height' => '0',
        'v_align' => 'top',
        'width' => 'normal',
        'align' => 'left',
        'divider' => 'none',
        'animation' => 'none',
    ];

    /**
     * The keys a character composes: every one that '' hands to it.
     *
     * @return list<string>
     */
    public static function composed(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * A stored style in its one shape: every key present, each '' or a value it may hold.
     * Unknown keys are dropped, and a value that is not one of the key's becomes ''.
     *
     * SHAPE ONLY. Whether the picture still exists is resolve()'s question, and whether an
     * anchor is used twice on one page is the page's (SectionForm::anchors).
     *
     * @return array<string, string|int|null>
     */
    public static function normalize(mixed $style): array
    {
        $style = is_array($style) ? $style : [];
        $normalized = [];
        foreach (self::composed() as $key) {
            $normalized[$key] = self::clean($key, $style[$key] ?? '');
        }
        foreach (self::HIDDEN as $key) {
            $normalized[$key] = ($style[$key] ?? '') === 'yes' ? 'yes' : '';
        }

        // A media id or nothing. A string of digits is accepted because that is what a
        // form sends; anything else, including 0 and a negative, becomes null.
        $image = $style[self::IMAGE] ?? null;
        $id = is_int($image) ? $image : (is_string($image) && ctype_digit($image) ? (int) $image : 0);
        $normalized[self::IMAGE] = $id > 0 ? $id : null;

        $name = $style[self::NAME] ?? '';
        $normalized[self::NAME] = is_string($name) ? mb_substr(trim((string) preg_replace('~\s+~u', ' ', $name)), 0, self::NAME_LENGTH) : '';
        $normalized[self::ANCHOR] = self::anchor($style[self::ANCHOR] ?? '');

        return $normalized;
    }

    /**
     * One composed key's value, or '' when it is none of the values the key may hold. A
     * number off its step is moved onto it rather than refused: a slider dragged by a
     * script that rounds differently is still the owner's choice.
     */
    public static function clean(string $key, mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (!is_string($value) || $value === '') {
            return '';
        }
        if (isset(self::OPTIONS[$key])) {
            return in_array($value, self::OPTIONS[$key], true) ? $value : '';
        }
        $range = self::NUMBERS[$key] ?? null;
        if ($range === null || !is_numeric($value)) {
            return '';
        }
        $number = (float) $value;
        if ($number < $range['min'] || $number > $range['max']) {
            return '';
        }

        return (string) (int) (round($number / $range['step']) * $range['step']);
    }

    /** An anchor as typed, made a slug; '' when nothing usable is left or it is reserved. */
    public static function anchor(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        // Made the way a page's address is made from its title, so "Naše usluge" is
        // nase-usluge here as it would be there.
        $slug = trim(substr(\App\Modules\Pages\Slug::fromTitle(ltrim(trim($value), '#')), 0, self::ANCHOR_LENGTH), '-');
        if ($slug === '' || preg_match(self::ANCHOR_PATTERN, $slug) !== 1) {
            return '';
        }
        foreach (self::RESERVED as $prefix) {
            if (str_starts_with($slug, $prefix)) {
                return '';
            }
        }

        return $slug;
    }

    /**
     * What the section is drawn with: the owner's value where there is one, the composed
     * value everywhere else.
     *
     * @param array<string, string|int|null> $style normalized
     * @param array<string, string> $composed what the character composes for this section
     * @return array<string, string|int|null>
     */
    public static function effective(array $style, array $composed): array
    {
        foreach (self::composed() as $key) {
            if (($style[$key] ?? '') === '') {
                $style[$key] = $composed[$key] ?? self::DEFAULTS[$key];
            }
        }

        return $style;
    }

    /**
     * Whether the owner has set anything the character would otherwise compose: what makes
     * the editor open a section's style rather than leave it folded.
     *
     * @param array<string, string|int|null> $style normalized
     */
    public static function overridden(array $style): bool
    {
        foreach (self::composed() as $key) {
            if (($style[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * The same style with every composed key handed back to the character: a reset. The
     * picture, the name, the anchor and the visibility are the owner's content and stay.
     *
     * @param array<string, string|int|null> $style normalized
     * @return array<string, string|int|null>
     */
    public static function reset(array $style): array
    {
        foreach (self::composed() as $key) {
            $style[$key] = '';
        }

        return $style;
    }

    /**
     * The same style with an image id that no longer names a picture set to null.
     *
     * Called where a database is at hand: on save, so nothing stored points at a deleted
     * picture. On render the question answers itself — the renderer looks the picture up,
     * finds nothing, and the section falls back to how `contrast` looks.
     *
     * @param array<string, string|int|null> $style normalized
     * @return array<string, string|int|null>
     */
    public static function resolve(Db $db, array $style): array
    {
        $id = $style[self::IMAGE] ?? null;
        if (!is_int($id) || $id <= 0 || $db->one('SELECT id FROM media WHERE id = ?', [$id]) === null) {
            $style[self::IMAGE] = null;
        }

        return $style;
    }

    /**
     * The class names of an effective style: the closed sets, the stepped numbers, and the
     * screens it is hidden on. Not the picture, which is rendered; not the animation and the
     * anchor, which are attributes().
     *
     * @param array<string, string|int|null> $style effective
     * @return list<string> e.g. surface-tinted, pad-t-120, min-h-50, hide-mobile
     */
    public static function classes(array $style): array
    {
        $classes = [];
        foreach (['surface', 'width', 'align', 'divider'] as $key) {
            $value = $style[$key] ?? '';
            $classes[] = $key . '-' . (is_string($value) && $value !== '' ? $value : self::DEFAULTS[$key]);
        }
        // '' is the section gap, which needs no class: it is what the padding falls back to.
        foreach (['pad_top' => 'pad-t-', 'pad_bottom' => 'pad-b-'] as $key => $prefix) {
            if (is_string($style[$key] ?? null) && $style[$key] !== '') {
                $classes[] = $prefix . $style[$key];
            }
        }
        $height = (int) ($style['min_height'] ?? 0);
        if ($height > 0) {
            $classes[] = 'min-h-' . $height;
            $classes[] = 'v-' . (is_string($style['v_align'] ?? null) && $style['v_align'] !== '' ? $style['v_align'] : 'top');
        }
        foreach (self::HIDDEN as $key) {
            if (($style[$key] ?? '') === 'yes') {
                $classes[] = str_replace('_', '-', $key);
            }
        }

        return $classes;
    }

    /**
     * The wrapper's attributes beyond its class, with their leading space: the anchor as
     * its id, and the animation for anim.js to run.
     *
     * @param array<string, string|int|null> $style effective
     */
    public static function attributes(array $style): string
    {
        $html = '';
        $anchor = $style[self::ANCHOR] ?? '';
        if (is_string($anchor) && $anchor !== '') {
            $html .= ' id="' . e($anchor) . '"';
        }
        $animation = $style['animation'] ?? 'none';
        if (is_string($animation) && $animation !== '' && $animation !== 'none') {
            $html .= ' data-anim="' . e($animation) . '"';
        }

        return $html;
    }
}
