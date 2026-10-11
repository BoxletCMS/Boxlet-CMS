<?php

namespace App\Modules\Appearance;

use App\Modules\Design\Characters;
use App\Modules\Design\Vocabulary\Decisions;
use App\Modules\Settings\ChromeLook;

/**
 * WHAT THE OWNER HAS MADE THEIR OWN, over the character the screen is showing (PLAN.md
 * D-158, D-164), and how the Appearance screen is cut into sections and groups (D-157).
 *
 * ONE MEANING OF "CHANGED" SINCE D-164. Every key is stored as '' while it follows — the
 * character, the typeface pairing, or the palette for a colour by hand — so changed is simply
 * "set, and not what it would follow": a dot, and a reset that writes ''. A value equal to
 * what it would follow is stored as '' (settle()), so there is one way to say "as the
 * character has it". Changing the character keeps every value the owner set.
 *
 * One class rather than several places that each know a part, because the dot, the counts,
 * the banner and the three resets all have to agree about which keys moved.
 */
final class Overrides
{
    /**
     * The sections in the order the home lists them, each a list of groups and each group the
     * keys it holds. Every global decision is in exactly one place (appearance_inspector_test).
     * A group with no keys holds something that is not a decision: the contrast check, the
     * diagram.
     */
    public const SECTIONS = [
        'colours' => [
            'basics' => ['seed', 'mode', 'secondary', 'surface_contrast'],
            'palette' => ['color_background', 'color_card', 'color_surface', 'color_border', 'color_text', 'color_muted', 'color_link'],
            'contrast' => [],
        ],
        'typography' => [
            'typeface' => ['heading_font', 'body_font'],
            'sizes' => ['text_size', 'scale', 'line_height'],
            'headings' => ['heading_weight', 'tracking', 'caps'],
            'fine' => ['nudge_h1', 'nudge_h2', 'nudge_sm'],
        ],
        'space' => [
            'spacing' => ['spacing', 'section_gap'],
            'shape' => ['radius', 'border_width'],
            'shadow' => ['shadow', 'shadow_strength'],
        ],
        'layout' => [
            'diagram' => [],
            'content' => ['container'],
            'page' => ['boxed', 'sheet_width', 'frame', 'sheet_gap', 'sheet_radius', 'sheet_shadow', 'page_background', 'page_background_colour'],
            'chrome' => ['header_bleed', 'header_width', 'footer_bleed', 'footer_width'],
        ],
        'header' => [
            'arrangement' => ['header_arrangement', 'brand', 'logo_size', 'header_height'],
            'behaviour' => ['header_behaviour', 'header_opacity', 'header_blur'],
            'background' => ['header_surface', 'header_colour', 'header_edge'],
            'menu' => ['nav_style', 'nav_ink', 'header_button'],
        ],
        'footer' => [
            'arrangement' => ['footer_layout', 'footer_columns', 'footer_links', 'small_print_row'],
            'background' => ['footer_surface', 'footer_colour', 'footer_edge'],
        ],
        'buttons' => [
            'style' => ['button_style', 'button_radius', 'button_height', 'button_caps'],
        ],
    ];

    /** 'by_hand' for a colour the palette works out while it is '', 'look' for the header and footer, else 'decision'. */
    public static function kind(string $key): string
    {
        if (in_array($key, ChromeLook::keys(), true)) {
            return 'look';
        }

        return Decisions::follows($key) === 'palette' ? 'by_hand' : 'decision';
    }

    /**
     * What each control is when the owner has not made it theirs: the character's value, the
     * font's for a key that follows a family (D-185), '' for a colour by hand (the palette's).
     *
     * @param string $heading the heading family the owner chose, '' for the character's
     * @param string $body the text's family the owner chose, '' for the character's
     * @return array<string, string>
     */
    public static function defaults(string $character, string $heading = '', string $body = ''): array
    {
        $values = Characters::decisions($character);
        $heading = $heading !== '' ? $heading : $values['heading_font'];
        $body = $body !== '' ? $body : $values['body_font'];
        $defaults = [];
        foreach (Decisions::ALL as $key => $definition) {
            $defaults[$key] = match (Decisions::follows($key)) {
                // A character may pin one (Bold's line height, D-185): then it is the character's.
                'font' => ($values[$key] ?? '') !== '' ? $values[$key] : Decisions::fromFont($key, $heading, $body),
                // A colour that follows the palette follows the CHARACTER'S own colour where it
                // names one (D-177). Brutalist names its footer's (D-172); counted as the
                // owner's because the palette's answer is '', it was stored as theirs and kept
                // through a change to Soft, which drew Brutalist's black footer under Soft.
                'palette' => $values[$key] ?? '',
                default => $values[$key],
            };
        }

        return $defaults;
    }

    /**
     * The keys the owner has set to something other than what they would follow, in the
     * order of SECTIONS.
     *
     * @param array<string, string> $values the owner's values, '' for what follows
     * @return list<string>
     */
    public static function changed(array $values, string $character): array
    {
        $defaults = self::defaults($character, $values['heading_font'] ?? '', $values['body_font'] ?? '');
        $changed = [];
        foreach (self::keys('all') ?? [] as $key) {
            $value = self::plain($key, $values[$key] ?? '', $defaults[$key] ?? '');
            if ($value !== '' && !self::same($value, $defaults[$key] ?? '')) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * How many of $changed fall in a section, or in one of its groups.
     *
     * @param list<string> $changed
     */
    public static function count(array $changed, string $section, ?string $group = null): int
    {
        $keys = $group === null
            ? array_merge(...array_values(self::SECTIONS[$section] ?? [[]]))
            : (self::SECTIONS[$section][$group] ?? []);

        // A dark colour of the owner's (D-187) counts where its colour does.
        return count(array_intersect(array_map(static fn (string $key): string => str_starts_with($key, 'dark_') ? substr($key, 5) : $key, $changed), $keys));
    }

    /**
     * The keys a reset reaches: `all`, `section:<name>`, or one key. Null for a scope that
     * names nothing, so a forged action is refused rather than read as "nothing to do".
     *
     * @return list<string>|null
     */
    public static function keys(string $scope): ?array
    {
        if ($scope === 'all') {
            $keys = [];
            foreach (self::SECTIONS as $groups) {
                foreach ($groups as $inGroup) {
                    array_push($keys, ...$inGroup);
                }
            }

            return $keys;
        }
        if (str_starts_with($scope, 'section:')) {
            $groups = self::SECTIONS[substr($scope, strlen('section:'))] ?? null;

            return $groups === null ? null : array_merge(...array_values($groups));
        }

        return in_array($scope, self::keys('all') ?? [], true) ? [$scope] : null;
    }

    /**
     * The owner's values with the keys in $scope put back: ''. Null when the scope names
     * nothing.
     *
     * @param array<string, string> $values
     * @return array<string, string>|null
     */
    public static function reset(array $values, string $scope): ?array
    {
        $keys = self::keys($scope);
        if ($keys === null) {
            return null;
        }
        foreach ($keys as $key) {
            $values[$key] = '';
        }

        return $values;
    }

    /**
     * The owner's values as stored (D-159, D-164): every value equal to what it would follow
     * is ''. The form posts what every control shows, which for a key nobody touched is the
     * character's or the font's value; storing that would pin it.
     *
     * @param array<string, string> $values
     * @return array<string, string>
     */
    public static function settle(array $values, string $character): array
    {
        $defaults = self::defaults($character, $values['heading_font'] ?? '', $values['body_font'] ?? '');
        foreach ($values as $key => $value) {
            $value = self::plain($key, $value, $defaults[$key] ?? '');
            $values[$key] = $value !== '' && isset($defaults[$key]) && self::same($value, $defaults[$key]) ? '' : $value;
        }

        return $values;
    }

    /** The section a key is in, or null. */
    public static function sectionOf(string $key): ?string
    {
        foreach (self::SECTIONS as $section => $groups) {
            foreach ($groups as $keys) {
                if (in_array($key, $keys, true)) {
                    return $section;
                }
            }
        }

        return null;
    }

    /** No second colour where the character has none either is no change at all. */
    private static function plain(string $key, string $value, string $default): string
    {
        return $key === 'secondary' && $value === 'none' && $default === '' ? '' : $value;
    }

    /**
     * Two stored values that mean the same: equal text in any case (a colour), or the same
     * number written two ways ("72" and "72.0").
     */
    private static function same(string $a, string $b): bool
    {
        if (strtolower($a) === strtolower($b)) {
            return true;
        }

        return is_numeric($a) && is_numeric($b) && abs((float) $a - (float) $b) < 1e-9;
    }
}
