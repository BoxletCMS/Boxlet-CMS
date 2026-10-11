<?php

namespace App\Modules\Design;

use App\Core\Blocks;
use App\Modules\Pages\SectionLayout;

/**
 * HOW WIDE WORDS RUN, WORKED OUT WITHOUT A BROWSER (PLAN.md D-182, D-183, D-184): the arithmetic
 * of the stylesheets written out, for bin/check-set.php and for SectionRender.
 *
 * words() is the grids alone: a section's width from the content width (narrow two thirds of
 * it, wide seven sixths, as Derived draws them, never wider than a boxed sheet), its columns
 * with their gap (sections.css), the share of a block's width its words get in each layout
 * (blocks*.css). SectionRender asks it whether a section of columns needs the wide measure.
 *
 * drawn() is what the page then draws, with the 16rem rules of D-184: the section widened,
 * the wide measure grown to what its columns need, columns and words beside something stacked
 * where they would fall under 16rem. A large screen is where words are narrowest beside
 * something; narrower screens are scenario 64's to measure.
 *
 * Not worked out: Call to action beside, whose buttons take the width their words need, and a
 * repeater's items, which have grids of their own (as in scenario 64).
 *
 * The grids were measured in the browser under Editorial (D-183), to the tenth of a rem: a
 * split hero 24.0, Form beside 14.8, a cover hero 28.0, the narrow column of wide-left 13.2.
 */
final class SetMeasure
{
    /** The narrowest a column of words may be, in rem (D-182). */
    public const LEAST = 16.0;

    /**
     * Layout => [share of the block's width the words get, the gap taken out first, in units
     * of the spacing decision], from the grid templates in blocks*.css. A layout not listed
     * gives its words the whole width.
     */
    private const SHARES = [
        // .layout-split .hero: 1.1fr 1fr, gap --space-columns (2.5 × spacing).
        'hero/split' => [1.1 / 2.1, 2.5],
        // .image-text: 1fr 1fr, gap --space-columns.
        'image_text/image-left' => [0.5, 2.5],
        'image_text/image-right' => [0.5, 2.5],
        // .layout-columns .richtext: columns 2, gap --space-xl (4 × spacing).
        'text/columns' => [0.5, 4.0],
        // .layout-beside .form-block: 2fr 3fr, gap --space-xl; the words are the 2.
        'form/beside' => [0.4, 4.0],
    ];

    /** A cover hero's words keep to the narrow measure (blocks-hero.css). */
    private const NARROW_MEASURE = ['hero/cover-center', 'hero/cover-left', 'hero/cover-right', 'hero/cover-low'];

    /**
     * Every place a set's choices put words in a column under 16rem, or stack a row because
     * they otherwise would (D-184: the last resort, worth an author's knowing), in words.
     *
     * @param array<string, string> $decisions resolved
     * @param array<string, mixed>|null $composition as DesignSet reads it
     * @param list<array<string, mixed>> $patterns as DesignSet reads them
     * @return list<string>
     */
    public static function narrow(array $decisions, ?array $composition, array $patterns, Blocks $registry): array
    {
        $found = [];
        $width = (string) ($composition['section']['width'] ?? 'normal');
        $say = static function (array $drawn, string $what) use (&$found): void {
            if ($drawn['words'] !== null && $drawn['words'] < self::LEAST - 0.05) {
                $found[] = sprintf('%s: words %.1frem wide', $what, $drawn['words']);
            }
            foreach ($drawn['stacked'] as $why) {
                $found[] = sprintf('%s: %s', $what, $why);
            }
        };
        foreach ($registry->types() as $type) {
            $composed = (string) ($composition['layouts'][$type] ?? $registry->layout($type, null));
            foreach ($registry->get($type)['layouts'] as $layout) {
                $chosen = $layout === $composed ? '' : ' if an owner chooses it';
                $say(self::drawn($decisions, $width, true, SectionLayout::ONE, 0, $type, $layout), "{$type}, {$layout}{$chosen}, in a {$width} section");
            }
        }
        foreach ($patterns as $pattern) {
            $style = $pattern['section']['style'] ?? [];
            $own = is_string($style['width'] ?? null) && $style['width'] !== '';
            foreach ($pattern['blocks'] as $block) {
                // A pattern has no pictures: its split hero is words alone, as wide as Left's.
                $layout = $block['type'] === 'hero' && $block['layout'] === 'split' ? 'left' : (string) $block['layout'];
                $say(
                    self::drawn($decisions, $own ? (string) $style['width'] : $width, !$own, (string) $pattern['section']['layout'], (int) $block['column'], (string) $block['type'], $layout),
                    sprintf('pattern %s: %s, %s, in column %d of %s', $pattern['id'], $block['type'], $block['layout'], $block['column'] + 1, $pattern['section']['layout']),
                );
            }
        }

        return $found;
    }

    /**
     * A block's words as the page draws them on a large screen (D-184): the section widened
     * for a split hero with its picture and for columns that need it (SectionRender, when its
     * width is the character's, `$following`), the wide measure grown to what the columns
     * need (sections-columns.css), a row of columns or words beside something stacked where
     * it would leave words under 16rem. `words` is null where not worked out; `stacked` says
     * what stacked and why.
     *
     * @param array<string, string> $decisions resolved
     * @return array{words: ?float, stacked: list<string>}
     */
    public static function drawn(array $decisions, string $width, bool $following, string $sectionLayout, int $column, string $type, string $layout): array
    {
        $unit = (float) $decisions['spacing'];
        $container = (float) $decisions['container'];
        $weights = SectionLayout::LAYOUTS[$sectionLayout] ?? [1];
        $count = count($weights);
        $stacked = [];
        if ($following && in_array($width, ['narrow', 'normal'], true)) {
            $narrowest = min(array_map(static fn (int $c): float => (float) self::words($decisions, $width, $sectionLayout, $c, 'text', 'single'), range(0, $count - 1)));
            if (($type === 'hero' && $layout === 'split') || ($count > 1 && $narrowest < self::LEAST)) {
                $width = 'wide';
            }
        }
        $section = self::section($decisions, $width);
        if ($width === 'wide' && $count > 1) {
            $need = self::LEAST * array_sum($weights) / min($weights) + ($count - 1) * 2 * $unit;
            $section = max($section, $need);
            if (($decisions['boxed'] ?? 'no') === 'yes') {
                $section = min($section, (float) $decisions['sheet_width'] - 4 * $unit);
            }
        }
        $gaps = ($count - 1) * 2 * $unit;
        if ($count > 1 && ($section - $gaps) * min($weights) / array_sum($weights) < self::LEAST - 0.001) {
            $stacked[] = sprintf('its columns stack, at %.1frem each beside one another', ($section - $gaps) * min($weights) / array_sum($weights));
            $available = $section;
        } else {
            $available = ($section - $gaps) * $weights[max(0, min($column, $count - 1))] / array_sum($weights);
        }

        $key = $type . '/' . $layout;
        if ($key === 'cta/beside') {
            return ['words' => null, 'stacked' => $stacked];
        }
        if (in_array($key, self::NARROW_MEASURE, true)) {
            return ['words' => min($available, $container * 2 / 3), 'stacked' => $stacked];
        }
        if (!isset(self::SHARES[$key])) {
            return ['words' => $available, 'stacked' => $stacked];
        }
        [$share, $gap] = self::SHARES[$key];
        $beside = ($available - $gap * $unit) * $share;
        // Where the stylesheets stack the words: the exact 16rem for Image and text and Text
        // in columns, the widest-gap widths for a split hero and Form beside.
        $stacks = match ($key) {
            'hero/split' => $available < 35.0,
            'form/beside' => $available < 47.0,
            default => $beside < self::LEAST,
        };
        if ($stacks) {
            $stacked[] = sprintf('its words stack, at %.1frem beside', $beside);

            return ['words' => $available, 'stacked' => $stacked];
        }

        return ['words' => $beside, 'stacked' => $stacked];
    }

    /**
     * A section's measure in rem: the content width, narrow two thirds of it, wide seven sixths.
     *
     * @param array<string, string> $decisions resolved
     */
    private static function section(array $decisions, string $width): float
    {
        $container = (float) $decisions['container'];
        $section = match ($width) {
            'narrow' => $container * 2 / 3,
            'wide', 'full' => $container * 7 / 6,
            default => $container,
        };
        if (($decisions['boxed'] ?? 'no') === 'yes') {
            // The sheet keeps the section's gutter, --space-l, on each side.
            $section = min($section, (float) $decisions['sheet_width'] - 2 * 2 * (float) $decisions['spacing']);
        }

        return $section;
    }

    /**
     * The width of a block's words in rem on a large screen by the grids alone, before anything
     * widens or stacks for them: what SectionRender asks to decide whether a section of columns
     * needs the wide measure. Null where it is not worked out.
     *
     * @param array<string, string> $decisions resolved
     */
    public static function words(array $decisions, string $width, string $sectionLayout, int $column, string $type, string $layout): ?float
    {
        if ($type === 'cta' && $layout === 'beside') {
            return null;
        }
        $unit = (float) $decisions['spacing'];
        $section = self::section($decisions, $width);
        // The section's columns, --space-l apart (sections.css).
        $weights = SectionLayout::LAYOUTS[$sectionLayout] ?? [1];
        $column = max(0, min($column, count($weights) - 1));
        $available = ($section - (count($weights) - 1) * 2 * $unit) * $weights[$column] / array_sum($weights);

        $key = $type . '/' . $layout;
        if (in_array($key, self::NARROW_MEASURE, true)) {
            return min($available, (float) $decisions['container'] * 2 / 3);
        }
        if (isset(self::SHARES[$key])) {
            [$share, $gap] = self::SHARES[$key];

            return ($available - $gap * $unit) * $share;
        }

        return $available;
    }
}
