<?php

namespace App\Modules\Design\Vocabulary;

use App\Modules\Design\CssNumber;
use App\Modules\Design\Fonts;

/**
 * EVERY GLOBAL DECISION, DEFINED ONCE (PLAN.md D-164; the rebuild's README 1.1–1.2).
 *
 * What a decision is — its group, its kind, its range and step or its closed set, what it is
 * when nothing says otherwise, and what '' means for it — lives here and nowhere else.
 * Validation, the design-set schema, the Appearance screen's sliders and segments, and the
 * screen's "changed" dots all read this table. It replaced named steps spread over Tokens'
 * constants, Derived's maps and ChromeLook's options, which were three lists of one thing.
 *
 * STORED AS NUMBERS. A slider's named steps survive only as the marks under it (`marks`):
 * labels for values, never values.
 *
 * '' MEANS "FOLLOW" for every key (D-158, D-159): the character's value (`follow:
 * character`), the font's (`font`, D-185: a heading's family for its treatment, the text's for
 * its line height), or the palette's (`palette`, a colour by hand). The site stores only what the owner set; changing the character keeps all of it.
 *
 * `part` says which half of a design-set file a key is written in — `decisions` or `look`,
 * the header and footer — which is the file's grouping and nothing more: one store holds both.
 */
final class Decisions
{
    /** The font library's families, as Fonts lists them (a test holds the two equal). */
    public const FONTS = [
        'playfair-display', 'source-serif-4', 'instrument-serif', 'young-serif', 'cormorant-garamond', 'bodoni-moda', 'newsreader', 'eb-garamond', 'libre-caslon-text',
        'inter', 'space-grotesk', 'nunito', 'hanken-grotesk', 'figtree', 'manrope', 'outfit', 'archivo', 'schibsted-grotesk', 'bricolage-grotesque', 'ibm-plex-sans',
        'big-shoulders-display', 'unbounded', 'syne', 'baloo-2',
        'ibm-plex-mono', 'jetbrains-mono', 'space-mono',
    ];

    /** Every decision: group, file part, kind, its range or set, its neutral value, what '' follows. */
    public const ALL = [
        // ---- colour ---------------------------------------------------------------------
        'seed' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'neutral' => '#3a4250'],
        // '' is no second colour; `none` is an owner saying so over a character that has one.
        'secondary' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'neutral' => ''],
        'mode' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'choice', 'values' => ['light', 'dark'], 'neutral' => 'light'],
        'surface_contrast' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => 1, 'unit' => '%', 'neutral' => '20', 'marks' => ['low' => 20, 'medium' => 50, 'high' => 80]],
        'color_background' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'color_card' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'color_surface' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'color_border' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'color_text' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'color_muted' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'color_link' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'page_background_colour' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'header_colour' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],
        'footer_colour' => ['group' => 'colour', 'part' => 'decisions', 'type' => 'colour', 'follow' => 'palette', 'neutral' => ''],

        // ---- type -----------------------------------------------------------------------
        // Two families from the library (D-185), where one of six pairings stood; a pairing is
        // a shortcut that sets both (Typography::pairing()).
        'heading_font' => ['group' => 'type', 'part' => 'decisions', 'type' => 'choice', 'values' => self::FONTS, 'neutral' => 'inter'],
        'body_font' => ['group' => 'type', 'part' => 'decisions', 'type' => 'choice', 'values' => self::FONTS, 'neutral' => 'inter'],
        'text_size' => ['group' => 'type', 'part' => 'decisions', 'type' => 'number', 'min' => 14, 'max' => 20, 'step' => 0.5, 'unit' => 'px', 'neutral' => '16', 'marks' => ['small' => 15, 'normal' => 16, 'large' => 17, 'larger' => 18]],
        'scale' => ['group' => 'type', 'part' => 'decisions', 'type' => 'number', 'min' => 1.1, 'max' => 1.6, 'step' => 0.005, 'neutral' => '1.2', 'marks' => ['gentle' => 1.125, 'clear' => 1.25, 'dramatic' => 1.414]],
        'line_height' => ['group' => 'type', 'part' => 'decisions', 'type' => 'number', 'min' => 1.3, 'max' => 1.9, 'step' => 0.05, 'follow' => 'font', 'neutral' => ''],
        'heading_weight' => ['group' => 'type', 'part' => 'decisions', 'type' => 'number', 'min' => 300, 'max' => 900, 'step' => 100, 'follow' => 'font', 'neutral' => ''],
        'tracking' => ['group' => 'type', 'part' => 'decisions', 'type' => 'number', 'min' => -0.05, 'max' => 0.1, 'step' => 0.005, 'unit' => 'em', 'follow' => 'font', 'neutral' => '', 'marks' => ['tight' => -0.03, 'normal' => 0, 'wide' => 0.06]],
        'caps' => ['group' => 'type', 'part' => 'decisions', 'type' => 'choice', 'values' => ['no', 'yes'], 'follow' => 'font', 'neutral' => ''],
        'nudge_h1' => ['group' => 'type', 'part' => 'decisions', 'type' => 'number', 'min' => -30, 'max' => 40, 'step' => 1, 'unit' => 'px', 'neutral' => '0'],
        'nudge_h2' => ['group' => 'type', 'part' => 'decisions', 'type' => 'number', 'min' => -12, 'max' => 20, 'step' => 1, 'unit' => 'px', 'neutral' => '0'],
        'nudge_sm' => ['group' => 'type', 'part' => 'decisions', 'type' => 'number', 'min' => -3, 'max' => 5, 'step' => 1, 'unit' => 'px', 'neutral' => '0'],

        // ---- space & shape --------------------------------------------------------------
        'spacing' => ['group' => 'space', 'part' => 'decisions', 'type' => 'number', 'min' => 0.75, 'max' => 1.75, 'step' => 0.05, 'unit' => 'rem', 'neutral' => '1', 'marks' => ['compact' => 0.875, 'normal' => 1, 'roomy' => 1.25, 'generous' => 1.5]],
        'section_gap' => ['group' => 'space', 'part' => 'decisions', 'type' => 'number', 'min' => 32, 'max' => 160, 'step' => 4, 'unit' => 'px', 'neutral' => '80', 'marks' => ['tight' => 48, 'normal' => 80, 'airy' => 120]],
        'radius' => ['group' => 'space', 'part' => 'decisions', 'type' => 'number', 'min' => 0, 'max' => 32, 'step' => 1, 'unit' => 'px', 'neutral' => '4', 'marks' => ['square' => 0, 'subtle' => 4, 'round' => 12]],
        'border_width' => ['group' => 'space', 'part' => 'decisions', 'type' => 'number', 'min' => 0, 'max' => 4, 'step' => 0.5, 'unit' => 'px', 'neutral' => '1'],
        'shadow' => ['group' => 'space', 'part' => 'decisions', 'type' => 'choice', 'values' => ['none', 'soft', 'hard', 'layered'], 'neutral' => 'none'],
        'shadow_strength' => ['group' => 'space', 'part' => 'decisions', 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => 1, 'unit' => '%', 'neutral' => '40'],

        // ---- layout ---------------------------------------------------------------------
        'container' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'number', 'min' => 36, 'max' => 88, 'step' => 2, 'unit' => 'rem', 'neutral' => '60', 'marks' => ['narrow' => 42, 'normal' => 60, 'wide' => 72]],
        'boxed' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'choice', 'values' => ['no', 'yes'], 'neutral' => 'no'],
        'sheet_width' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'number', 'min' => 40, 'max' => 120, 'step' => 2, 'unit' => 'rem', 'neutral' => '80'],
        'frame' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'number', 'min' => 0, 'max' => 6, 'step' => 0.25, 'unit' => 'rem', 'neutral' => '3'],
        'sheet_gap' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'number', 'min' => 0, 'max' => 8, 'step' => 1, 'neutral' => '3'],
        'sheet_radius' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'number', 'min' => 0, 'max' => 32, 'step' => 1, 'unit' => 'px', 'neutral' => '0'],
        'sheet_shadow' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'choice', 'values' => ['none', 'shadow', 'hairline'], 'neutral' => 'none'],
        'page_background' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'choice', 'values' => ['surface', 'border', 'contrast'], 'neutral' => 'surface'],
        'header_bleed' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'choice', 'values' => ['sheet', 'full'], 'neutral' => 'sheet'],
        'header_width' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'choice', 'values' => ['content', 'sheet', 'window'], 'neutral' => 'content'],
        'footer_bleed' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'choice', 'values' => ['sheet', 'full'], 'neutral' => 'sheet'],
        'footer_width' => ['group' => 'layout', 'part' => 'decisions', 'type' => 'choice', 'values' => ['content', 'sheet', 'window'], 'neutral' => 'content'],

        // ---- buttons --------------------------------------------------------------------
        'button_style' => ['group' => 'buttons', 'part' => 'decisions', 'type' => 'choice', 'values' => ['filled', 'outline', 'soft'], 'neutral' => 'filled'],
        // 28 is "pill": Derived writes 999px for it.
        'button_radius' => ['group' => 'buttons', 'part' => 'decisions', 'type' => 'number', 'min' => 0, 'max' => 28, 'step' => 1, 'unit' => 'px', 'neutral' => '6', 'marks' => ['square' => 0, 'pill' => 28]],
        'button_height' => ['group' => 'buttons', 'part' => 'decisions', 'type' => 'number', 'min' => 32, 'max' => 60, 'step' => 2, 'unit' => 'px', 'neutral' => '44'],
        'button_caps' => ['group' => 'buttons', 'part' => 'decisions', 'type' => 'choice', 'values' => ['no', 'yes'], 'neutral' => 'no'],

        // ---- the header -----------------------------------------------------------------
        'header_arrangement' => ['group' => 'header', 'part' => 'look', 'type' => 'choice', 'values' => ['left', 'inline', 'centred', 'split', 'masthead'], 'neutral' => 'left'],
        'header_behaviour' => ['group' => 'header', 'part' => 'look', 'type' => 'choice', 'values' => ['static', 'sticky', 'over'], 'neutral' => 'static'],
        'header_opacity' => ['group' => 'header', 'part' => 'look', 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => 1, 'unit' => '%', 'neutral' => '100'],
        'header_blur' => ['group' => 'header', 'part' => 'look', 'type' => 'number', 'min' => 0, 'max' => 24, 'step' => 1, 'unit' => 'px', 'neutral' => '0'],
        'header_surface' => ['group' => 'header', 'part' => 'look', 'type' => 'choice', 'values' => ['plain', 'tinted', 'contrast', 'gradient'], 'neutral' => 'plain'],
        'header_edge' => ['group' => 'header', 'part' => 'look', 'type' => 'choice', 'values' => ['none', 'line', 'shadow'], 'neutral' => 'none'],
        'header_height' => ['group' => 'header', 'part' => 'look', 'type' => 'number', 'min' => 48, 'max' => 120, 'step' => 2, 'unit' => 'px', 'neutral' => '72', 'marks' => ['compact' => 56, 'normal' => 72, 'roomy' => 92]],
        'logo_size' => ['group' => 'header', 'part' => 'look', 'type' => 'number', 'min' => 20, 'max' => 72, 'step' => 1, 'unit' => 'px', 'neutral' => '32', 'marks' => ['small' => 32, 'medium' => 44, 'large' => 60]],
        'brand' => ['group' => 'header', 'part' => 'look', 'type' => 'choice', 'values' => ['logo', 'name', 'both'], 'neutral' => 'logo'],
        'nav_style' => ['group' => 'header', 'part' => 'look', 'type' => 'choice', 'values' => ['plain', 'caps', 'pills', 'bar', 'chips'], 'neutral' => 'plain'],
        'nav_ink' => ['group' => 'header', 'part' => 'look', 'type' => 'choice', 'values' => ['accent', 'ink'], 'neutral' => 'ink'],
        'header_button' => ['group' => 'header', 'part' => 'look', 'type' => 'choice', 'values' => ['filled', 'outline', 'text', 'none'], 'neutral' => 'filled'],

        // ---- the footer -----------------------------------------------------------------
        'footer_layout' => ['group' => 'footer', 'part' => 'look', 'type' => 'choice', 'values' => ['simple', 'centred', 'columns', 'menu_first', 'three'], 'neutral' => 'simple'],
        'footer_columns' => ['group' => 'footer', 'part' => 'look', 'type' => 'choice', 'values' => ['2', '3', '4'], 'neutral' => '3'],
        'footer_links' => ['group' => 'footer', 'part' => 'look', 'type' => 'choice', 'values' => ['auto', 'list'], 'neutral' => 'auto'],
        'small_print_row' => ['group' => 'footer', 'part' => 'look', 'type' => 'choice', 'values' => ['left', 'split', 'centred'], 'neutral' => 'split'],
        'footer_surface' => ['group' => 'footer', 'part' => 'look', 'type' => 'choice', 'values' => ['plain', 'tinted', 'contrast', 'gradient'], 'neutral' => 'plain'],
        'footer_edge' => ['group' => 'footer', 'part' => 'look', 'type' => 'choice', 'values' => ['none', 'line', 'slant', 'curve'], 'neutral' => 'none'],
    ];

    /**
     * WHAT A SET'S DARK VERSION MAY SAY (D-185, the owner): its own main and second colour,
     * colours by hand, the header's and footer's colours, the surface contrast and the
     * shadow's strength. In dark mode they stand for the light ones; a colour left out is the
     * palette's, and anything else left out the light version's.
     */
    public const DARK = ['seed', 'secondary', 'color_background', 'color_card', 'color_surface', 'color_border', 'color_text', 'color_muted', 'color_link', 'header_colour', 'footer_colour', 'surface_contrast', 'shadow_strength'];

    /**
     * The colours the owner may give a dark value of their own (D-187, the owner): the palette's
     * roles and the header's and footer's own colours, every colour by hand that a dark version
     * may hold. A hand colour holds in both modes until one is set while Appearance is in Dark;
     * that one is for dark mode only.
     */
    public const DARK_OWN = ['color_background', 'color_card', 'color_surface', 'color_border', 'color_text', 'color_muted', 'color_link', 'header_colour', 'footer_colour'];

    /** The colours that may be set by hand, '' while the palette decides. */
    public const BY_HAND = ['background', 'card', 'surface', 'border', 'text', 'muted', 'link'];

    /** The places that may take a colour of their own (D-076). */
    public const OWN_COLOURS = ['page_background_colour', 'header_colour', 'footer_colour'];

    /**
     * The keys of one part of a design-set file, in the table's order.
     *
     * @return list<string>
     */
    public static function keys(?string $part = null): array
    {
        $keys = [];
        foreach (self::ALL as $key => $definition) {
            if ($part === null || $definition['part'] === $part) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * What each key is when nothing says otherwise.
     *
     * @return array<string, string>
     */
    public static function neutral(): array
    {
        return array_map(static fn (array $definition): string => $definition['neutral'], self::ALL);
    }

    /**
     * One value, checked against its definition: the value as stored, or null when it is not
     * one the decision takes. '' is always accepted: it is "follow".
     */
    public static function clean(string $key, mixed $value): ?string
    {
        $definition = self::ALL[$key] ?? null;
        if ($definition === null) {
            return null;
        }
        if ($value === '' || $value === null) {
            return '';
        }
        if ($definition['type'] === 'choice') {
            return is_string($value) && in_array($value, $definition['values'], true) ? $value : null;
        }
        if ($definition['type'] === 'colour') {
            if ($key === 'secondary' && $value === 'none') {
                return 'none';
            }

            return is_string($value) ? \App\Modules\Design\Color::normalizeHex($value) : null;
        }
        if (is_bool($value) || is_array($value) || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        $min = (float) $definition['min'];
        $max = (float) $definition['max'];
        if ($number < $min - 1e-9 || $number > $max + 1e-9) {
            return null;
        }
        $step = (float) $definition['step'];

        // Rounded to the step it is offered in, counted from the bottom of the range.
        return CssNumber::of($min + round(($number - $min) / $step) * $step, 3);
    }

    /**
     * What a key follows when it is '': the character's value, the font's, or the palette.
     */
    public static function follows(string $key): string
    {
        return self::ALL[$key]['follow'] ?? 'character';
    }

    /**
     * A font's own value for a key that follows it (D-185), as stored would write it: what the
     * screen shows on a slider nobody has moved. A heading's treatment is its family's, a
     * paragraph's line height the text's family's.
     */
    public static function fromFont(string $key, string $heading, string $body): string
    {
        $face = Fonts::ALL[Fonts::known($heading)]['heading'];

        return match ($key) {
            'line_height' => CssNumber::of((float) Fonts::ALL[Fonts::known($body)]['leading']),
            'heading_weight' => CssNumber::of(round($face[0] / 100) * 100),
            'tracking' => CssNumber::of((float) $face[1]),
            'caps' => $face[3] ? 'yes' : 'no',
            default => '',
        };
    }
}
