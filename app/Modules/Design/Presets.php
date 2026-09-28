<?php

namespace App\Modules\Design;

/**
 * Layer 0: the five characters. Each is a complete set of layer-1 decisions plus the
 * composition it gives a page: the layer-2 section style and layer-3 layout new blocks
 * start from (SPEC §5.4).
 *
 * They differ in structure, not only hue. Tokens change type, text size, scale, spacing,
 * radius, shadow, content width and surface contrast; composition changes the shape of the
 * page itself: measure, vertical rhythm, alignment, section boundaries and hero arrangement.
 *
 * THE CONTENT WIDTH IS A NUMBER OF REM (D-062), not one of four names. The numbers here are
 * exactly what the names meant, so no character's page moved when the decision changed
 * shape. Editorial is the one that takes a larger base text size — long-form reading is what
 * it is for, and a control no character demonstrates is a control nobody finds.
 */
final class Presets
{
    public const DEFAULT = 'minimal';

    /** Every character derives its whole palette: a colour set by hand is the owner's, and
     *  a character that shipped one would be making that choice for them (D-063). */
    /** No character nudges a step or overrides the pairing's heading treatment: those are
     *  the owner's exceptions to what a character gives (D-066). */
    /* The sheet as every character has drawn it until now: three units of frame, square
     * corners, no lift, chrome inside the sheet. Soft is the one that is boxed, so it is the
     * one where any of this shows (D-067). */
    private const SHEET = [
        'frame' => 'normal', 'sheet_width' => '80', 'sheet_gap' => '3', 'sheet_radius' => 'square', 'sheet_shadow' => 'none',
        'header_bleed' => 'sheet', 'footer_bleed' => 'sheet',
        // The footer's contents line up with the page's content unless the owner says
        // otherwise (D-116): every character had that and keeps it.
        'footer_width' => 'content',
    ];

    private const NOTHING_NUDGED = [
        'nudge_h1' => '0', 'nudge_h2' => '0', 'nudge_sm' => '0',
        'heading_weight' => '', 'tracking' => '', 'caps' => '',
    ];

    private const NO_COLOURS_BY_HAND = [
        'color_background' => '', 'color_card' => '', 'color_surface' => '', 'color_border' => '',
        'color_text' => '', 'color_muted' => '', 'color_link' => '',
    ];

    /*
     * PRIVATE, so there is ONE way to obtain a character and it is always the complete set.
     * The six hand-set colour roles are merged in by get() (D-063), and a literal read
     * directly is a decision set with six keys missing — which is exactly what a test did,
     * and it failed for the right reason.
     */
    private const ALL = [
        // Long-form reading: high-contrast serifs on a wide scale, a narrow measure,
        // generous air, barely softened corners, no shadows, quiet surfaces.
        'editorial' => [
            'seed' => '#8a1c2b', 'secondary' => '#1f1a17', 'typography' => 'editorial', 'text_size' => 'large', 'scale' => '1.333',
            'spacing' => 'roomy', 'radius' => 'subtle', 'shadow' => 'none', 'container' => '42', 'surface_contrast' => 'low',
            'header_width' => 'content', 'boxed' => 'no', 'page_background' => 'surface',
        ],
        // Restraint: near-neutral graphite, one sans family on a tight scale, even spacing,
        // barely rounded, flat.
        'minimal' => [
            'seed' => '#3a4250', 'secondary' => '', 'typography' => 'modern', 'text_size' => 'normal', 'scale' => '1.2',
            'spacing' => 'normal', 'radius' => 'subtle', 'shadow' => 'none', 'container' => '56', 'surface_contrast' => 'low',
            'header_width' => 'content', 'boxed' => 'no', 'page_background' => 'surface',
        ],
        // Loud: a condensed-feeling grotesk on the steepest scale, saturated violet,
        // round corners, layered depth, wide sections, strongly separated surfaces.
        'bold' => [
            'seed' => '#6d28d9', 'secondary' => '#1e1045', 'typography' => 'grotesk', 'text_size' => 'normal', 'scale' => '1.5',
            'spacing' => 'normal', 'radius' => 'round', 'shadow' => 'layered', 'container' => '68', 'surface_contrast' => 'high',
            'header_width' => 'window', 'boxed' => 'no', 'page_background' => 'contrast',
        ],
        // Gentle: sage green, a rounded family, generous spacing, pill shapes, soft
        // shadows, a pale cream second colour for contrast sections.
        // The one character that ships BOXED, so the decision is visible to an owner who
        // never goes looking for it. A feature no default demonstrates is a feature nobody
        // finds.
        'soft' => [
            'seed' => '#3b6b4f', 'secondary' => '#f5ecdc', 'typography' => 'rounded', 'text_size' => 'normal', 'scale' => '1.25',
            'spacing' => 'generous', 'radius' => 'pill', 'shadow' => 'soft', 'container' => '56', 'surface_contrast' => 'medium',
            'header_width' => 'content', 'boxed' => 'yes', 'page_background' => 'surface',
        ],
        // Raw: monospace in capitals, compact spacing on a big scale, hard offset shadows
        // and thick rules, square corners, full-width, pure blue on stark yellow.
        'brutalist' => [
            'seed' => '#1f1fd1', 'secondary' => '#ffe600', 'typography' => 'mono', 'text_size' => 'normal', 'scale' => '1.414',
            'spacing' => 'compact', 'radius' => 'none', 'shadow' => 'hard', 'container' => '80', 'surface_contrast' => 'high',
            'header_width' => 'window', 'boxed' => 'no', 'page_background' => 'border',
        ],
    ];

    /**
     * Layers 2 and 3 per character. `section` is the section style every block starts
     * from, `surfaces` overrides the surface for one block type, and `layouts` picks the
     * arrangement of a block type that offers several. A block type named nowhere takes
     * `section` and its own default layout, so a character keeps working when Slice 9
     * adds six more blocks.
     *
     * The five differ in measure, rhythm, alignment, section boundary and hero
     * arrangement, so switching character changes how a page is composed and not only
     * how it is painted.
     */
    public const COMPOSITION = [
        // A reading column: a measure set by the character's own narrow container token,
        // plenty of air, everything ranged left, no rules. The page is one quiet text.
        'editorial' => [
            'section' => ['surface' => 'plain', 'rhythm' => 'airy', 'width' => 'normal', 'align' => 'left', 'divider' => 'none'],
            'surfaces' => ['image_text' => 'tinted'],
            'dividers' => [],
            'layouts' => ['hero' => 'left', 'image_text' => 'image-left', 'text' => 'single'],
        ],
        // Quiet on purpose, which is not the same as unstyled: a lot of air, everything
        // centred on one axis, one tinted panel for tone, and a hairline marking two
        // transitions rather than all of them.
        'minimal' => [
            'section' => ['surface' => 'plain', 'rhythm' => 'airy', 'width' => 'normal', 'align' => 'center', 'divider' => 'none'],
            'surfaces' => ['text' => 'tinted'],
            'dividers' => ['hero' => 'line', 'text' => 'line'],
            'layouts' => ['hero' => 'center', 'image_text' => 'image-left', 'text' => 'single'],
        ],
        // Big and confident: a wide measure, ranged left so headlines run long, the
        // surfaces used hard, and a slant where the tone changes.
        'bold' => [
            'section' => ['surface' => 'plain', 'rhythm' => 'normal', 'width' => 'wide', 'align' => 'left', 'divider' => 'none'],
            'surfaces' => ['hero' => 'gradient', 'image_text' => 'contrast', 'text' => 'tinted'],
            'dividers' => ['hero' => 'slant', 'text' => 'slant'],
            'layouts' => ['hero' => 'center', 'image_text' => 'image-left', 'text' => 'single'],
        ],
        // Relaxed and open: airy rhythm, split heroes, and a curve used as an accent on
        // a couple of transitions. A curve on every boundary reads as a stack of
        // lozenges rather than as a style.
        'soft' => [
            'section' => ['surface' => 'plain', 'rhythm' => 'airy', 'width' => 'normal', 'align' => 'left', 'divider' => 'none'],
            'surfaces' => ['hero' => 'tinted', 'text' => 'tinted'],
            'dividers' => ['hero' => 'curve', 'text' => 'curve'],
            'layouts' => ['hero' => 'split', 'image_text' => 'image-right', 'text' => 'single'],
        ],
        // Dense and flush: full-bleed sections, tight rhythm, no dividers at all, split
        // heroes and slabs of contrast. Edge to edge, nothing centred.
        'brutalist' => [
            'section' => ['surface' => 'plain', 'rhythm' => 'tight', 'width' => 'full', 'align' => 'left', 'divider' => 'none'],
            'surfaces' => ['hero' => 'contrast', 'text' => 'tinted'],
            'dividers' => [],
            'layouts' => ['hero' => 'split', 'image_text' => 'image-left', 'text' => 'columns'],
        ],
    ];

    /**
     * The layer-1 decisions of a character, falling back to the default character.
     *
     * @return array<string, string>
     */
    public static function get(string $name): array
    {
        $preset = self::ALL[$name] ?? self::ALL[self::DEFAULT];

        // Merged here rather than written out five times: six empty strings repeated in
        // every character would say nothing except "this character makes no choice", which
        // is the default for all of them. The order is the one validate() stores in.
        // THE ORDER validate() STORES IN, spelled once: what a character gives, with the
        // decisions no character makes merged into their places rather than appended. A
        // test compares a character with what validation returns, and an array whose keys
        // are in another order is a different array.
        return ['seed' => $preset['seed'], 'secondary' => $preset['secondary']]
            + self::NO_COLOURS_BY_HAND
            + ['typography' => $preset['typography'], 'text_size' => $preset['text_size'], 'scale' => $preset['scale']]
            + self::NOTHING_NUDGED
            + $preset
            /* NO CHARACTER GIVES ONE OF THE THREE A COLOUR OF ITS OWN (D-076). The shades of
               the palette are what a character IS; one shipping a free colour would be
               making the owner's exception for them, exactly as a character shipping a
               hand-set role would (D-063). Split in two because they sit on either side of
               the sheet in the order validate() stores. */
            + ['page_background_colour' => '']
            + self::SHEET
            + ['header_colour' => '', 'footer_colour' => ''];
    }

    public static function exists(string $name): bool
    {
        return isset(self::ALL[$name]);
    }

    /**
     * The section edge a character uses as its accent: the shape it draws where the tone
     * changes, rather than on every boundary. 'none' when it draws no edges at all.
     *
     * A divider marks a transition, so using one everywhere is the same as using none:
     * the eye stops reading it as a boundary.
     */
    public static function dividerAccent(string $name): string
    {
        $composition = self::COMPOSITION[$name] ?? self::COMPOSITION[self::DEFAULT];
        // The map lists only the transitions a character actually draws, so its first
        // entry is the accent. A character that draws none falls back to its section
        // default, which is 'none' for all of them today.
        $accents = array_values($composition['dividers']);

        return $accents === [] ? $composition['section']['divider'] : $accents[0];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::ALL);
    }
}
