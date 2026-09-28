<?php

namespace App\Modules\Design;

/**
 * What the decisions PRODUCE: every custom property TokenCompiler writes (SPEC §5.4).
 *
 * Its own file because it is its own question. Tokens answers "is this a decision Boxlet
 * accepts, and what does it mean to a person"; this answers "what CSS does it come to". The
 * two grew into one file of 355 lines, which is where a file stops being read and starts
 * being searched (CLAUDE.md), and they were never the same concern.
 *
 * NOTHING HERE VALIDATES. Everything it is handed has already been through
 * Tokens::validate(), which is what lets these methods read as arithmetic rather than as a
 * second opinion about what a decision may be.
 */
final class Derived
{
    /** Type steps as powers of the scale ratio, from small print to the largest heading. */
    public const TYPE_STEPS = ['sm' => -1, 'base' => 0, 'lg' => 1, 'xl' => 2, '2xl' => 3, '3xl' => 4, '4xl' => 5];
    public const SPACE_STEPS = ['xs' => 0.25, 's' => 0.5, 'm' => 1, 'l' => 2, 'xl' => 4, '2xl' => 6, '3xl' => 8];
    public const RADII = [
        'none' => ['s' => '0', 'm' => '0', 'l' => '0', 'button' => '0'],
        'subtle' => ['s' => '0.125rem', 'm' => '0.25rem', 'l' => '0.5rem', 'button' => '0.25rem'],
        'round' => ['s' => '0.375rem', 'm' => '0.75rem', 'l' => '1.25rem', 'button' => '0.75rem'],
        'pill' => ['s' => '0.5rem', 'm' => '1rem', 'l' => '2rem', 'button' => '999rem'],
    ];

    /**
     * Every custom property the decisions produce, for TokenCompiler: group => name =>
     * value, emitted as --group-name.
     *
     * @param array<string, string> $decisions validated decisions
     * @return array<string, array<string, string>>
     */
    public static function from(array $decisions): array
    {
        $colors = Palette::colors($decisions['seed'], $decisions['secondary'], $decisions['surface_contrast'], Tokens::byHand($decisions));
        $pairing = Typography::PAIRINGS[$decisions['typography']];
        $width = Tokens::width($decisions['container']) ?? 56.0;
        $hard = $decisions['shadow'] === 'hard';

        return [
            'color' => $colors,
            'font' => ['heading' => Typography::stack($pairing['heading']), 'body' => Typography::stack($pairing['body'])],
            // The pairing's own treatment, unless the owner has taken one over (D-066).
            'heading' => [
                'weight' => $decisions['heading_weight'] !== '' ? $decisions['heading_weight'] : $pairing['heading_weight'],
                'tracking' => Tokens::TRACKING[$decisions['tracking']] ?? $pairing['tracking'],
                'transform' => Tokens::CAPS[$decisions['caps']] ?? $pairing['transform'],
            ],
            'body' => ['weight' => $pairing['body_weight']],
            'leading' => ['body' => $pairing['leading_body'], 'heading' => $pairing['leading_heading']],
            'text' => self::typeScale($decisions),
            'space' => self::spaceScale(Tokens::SPACING[$decisions['spacing']]),
            'radius' => self::RADII[$decisions['radius']],
            'shadow' => self::shadows($decisions['shadow'], $colors['text']),
            // Hard shadows come with heavy rules and outlined cards; everything else is hairline.
            'border' => ['width' => $hard ? '3px' : '1px', 'card' => $hard ? '3px' : '0px'],
            'container' => ['width' => self::rem($width), 'narrow' => self::rem($width * 0.68), 'wide' => self::rem($width * 1.3)],
            'page' => self::page($decisions, $colors, Tokens::SPACING[$decisions['spacing']]),
            'chrome' => self::chrome($decisions, $colors),
        ];
    }

    /**
     * The header's and the footer's own colours, and the ink derived for them (D-076).
     *
     * EMITTED ONLY WHEN THE OWNER SET ONE, which is what makes this need no rule of its own
     * and no class on the element. chrome.css reads every one of these through
     * `var(--chrome-header-bg, <what the surface class gave>)`, so a token that is not here
     * is not a colour that is wrong — it is the palette's shade, standing exactly as before.
     *
     * The three inks are the same three the contrast surface has always had, from the same
     * function (Palette::inksOn): a surface that carries text needs an ink that can be read
     * on it, a muted one beside it and a raised one for whatever sits on top.
     *
     * @param array<string, string> $decisions
     * @param array<string, string> $colors
     * @return array<string, string>
     */
    private static function chrome(array $decisions, array $colors): array
    {
        $tokens = [];
        foreach (Tokens::ownChrome($decisions) as $part => $surface) {
            $inks = Palette::inksOn($surface, $colors);
            $tokens[$part . '-bg'] = $surface;
            $tokens[$part . '-text'] = $inks['text'];
            $tokens[$part . '-muted'] = $inks['muted'];
            $tokens[$part . '-raised'] = $inks['raised'];
        }

        return $tokens;
    }

    /**
     * The page as a sheet (D-031): what sits around it, how far it is inset, and how wide
     * the header runs.
     *
     * THE FRAME IS ZERO WHEN THE PAGE IS NOT BOXED, which is what makes the background
     * decision harmless rather than conditional: there is no area around the sheet, so the
     * colour has nothing to paint and no text can land on it. One value decides it, in one
     * place, instead of every rule asking whether boxing is on.
     *
     * @param array<string, string> $decisions
     * @param array<string, string> $colors
     * @return array<string, string>
     */
    private static function page(array $decisions, array $colors, float $spacingUnit): array
    {
        $boxed = $decisions['boxed'] === 'yes';

        return [
            // A shade of the palette, or the owner's own colour where they gave one (D-076).
            // Nothing else changes: this is the one value the whole boxed-page decision
            // runs through, so a free colour here needs no second rule anywhere.
            'bg' => $decisions['page_background_colour'] !== ''
                ? $decisions['page_background_colour']
                : ($colors[$decisions['page_background']] ?? $colors['surface']),
            'frame' => $boxed ? self::rem($spacingUnit * (Tokens::FRAME[$decisions['frame']] ?? 3.0)) : '0',
            // The room above and below the sheet, apart from the sides (D-116): zero glues a
            // header or footer that breaks out of the sheet to it.
            'frame-block' => $boxed ? self::rem($spacingUnit * (float) $decisions['sheet_gap']) : '0',
            // The sheet's own width, centred in the window; `none` when it is not boxed, so
            // the sheet fills the window as it always did (D-116).
            'sheet-width' => $boxed ? self::rem((float) $decisions['sheet_width']) : 'none',
            // The sheet's own corners and lift, and both are ZERO WHEN IT IS NOT BOXED for
            // the same reason the frame is: an unboxed sheet fills the window, and a
            // rounded corner or a shadow on something with no edge visible is a rule that
            // does nothing but has to be read by everyone after (D-067).
            'sheet-radius' => $boxed ? match ($decisions['sheet_radius']) {
                'round' => self::RADII['round']['l'],
                'soft' => self::RADII['subtle']['l'],
                default => '0',
            } : '0',
            'sheet-shadow' => $boxed ? match ($decisions['sheet_shadow']) {
                'shadow' => self::shadows('soft', $colors['text'])['l'],
                'hairline' => '0 0 0 1px ' . $colors['border'],
                default => 'none',
            } : 'none',
            // The sheet keeps the page background; only what surrounds it changes.
            'sheet' => $colors['background'],
            // "Full width" on a boxed page is the sheet's width (D-116, the owner's detail):
            // a header as wide as the window over a box narrower than it lined up with
            // nothing. Less the container's own side padding, because the container is
            // content-box and its padding would otherwise stand OUTSIDE the sheet's edge —
            // measured: 1464px of header over a 1408px sheet. Unboxed, the sheet is the
            // window and full is 100%, which an auto width already keeps inside it.
            'header-width' => self::chromeWidth($decisions['header_width'], $decisions, $boxed),
            // And the footer's contents, by the same two answers (D-116).
            'footer-width' => self::chromeWidth($decisions['footer_width'], $decisions, $boxed),
        ];
    }

    /**
     * How wide the header's or the footer's contents run (D-031, D-116): the page's content
     * column, or "full" — the sheet's width on a boxed page, less the container's own side
     * padding, because the container is content-box and its padding would otherwise stand
     * outside the sheet's edge (measured: 1464px of header over a 1408px sheet); 100% when
     * the page is not boxed, which an auto width already keeps inside the window.
     *
     * @param array<string, string> $decisions
     */
    private static function chromeWidth(string $choice, array $decisions, bool $boxed): string
    {
        // `window` (D-123): as wide as the bar it stands in — the window when the bar runs
        // across it, the sheet when the bar is inside it.
        if ($choice === 'window') {
            return '100%';
        }
        if ($choice !== 'full') {
            return self::rem(Tokens::width($decisions['container']) ?? 56.0);
        }

        return $boxed ? 'calc(' . self::rem((float) $decisions['sheet_width']) . ' - 2 * var(--space-l))' : '100%';
    }

    /**
     * The nudges in rem, keyed by the step each one moves (D-066). Pixels on the screen,
     * because that is what a person is nudging; rem here, because that is what the scale is
     * in and 16 is the root the whole model assumes.
     *
     * @param array<string, string> $decisions
     * @return array<string, float>
     */
    private static function nudges(array $decisions): array
    {
        $nudges = [];
        foreach (Tokens::NUDGES as $key => $bounds) {
            $nudges[$bounds['step']] = (float) ($decisions[$key] ?? 0) / 16;
        }

        return $nudges;
    }

    /**
     * ONE PLACE WHERE A SIZE IS WORKED OUT (D-066), in rem.
     *
     * The compiler needs it as CSS and the screen needs it as a number a person reads, and
     * for a while they each did the arithmetic. The screen's copy was written first and did
     * not know about the nudges, so every readout in the Type tab was wrong the moment one
     * was used — caught by a test within a minute of the nudges existing. Two copies of a
     * formula are two answers waiting to differ.
     *
     * @param array<string, string> $decisions validated decisions
     */
    public static function sizeOf(array $decisions, string $step): float
    {
        $ratio = (float) $decisions['scale'];
        $base = Tokens::TEXT_SIZE[$decisions['text_size']] ?? 1.0;
        $nudges = self::nudges($decisions);

        // The nudge lands AFTER the ratio, so the scale stays the relationship it is and the
        // nudge stays the exception it is. Never below half a rem: a size of zero is not a
        // smaller heading, it is a missing one.
        return max(0.5, $base * $ratio ** self::TYPE_STEPS[$step] + ($nudges[$step] ?? 0.0));
    }

    /**
     * @param array<string, string> $decisions
     * @return array<string, string>
     */
    private static function typeScale(array $decisions): array
    {
        $sizes = [];
        foreach (self::TYPE_STEPS as $name => $step) {
            $size = self::sizeOf($decisions, $name);
            if ($step < 3) {
                $sizes[$name] = self::rem($size);
                continue;
            }
            // Headings shrink on narrow screens: 72% at a 30rem viewport, full size from 75rem.
            $min = max(1.25, $size * 0.72);
            $slope = ($size - $min) / 0.45;
            $sizes[$name] = sprintf('clamp(%s, %s + %svw, %s)', self::rem($min), self::rem($min - 0.3 * $slope), self::number($slope), self::rem($size));
        }

        return $sizes;
    }

    /**
     * @return array<string, string>
     */
    private static function spaceScale(float $unit): array
    {
        return array_map(static fn (float|int $factor): string => self::rem($unit * $factor), self::SPACE_STEPS);
    }

    /**
     * @return array<string, string>
     */
    private static function shadows(string $character, string $ink): array
    {
        $rgb = Color::channels($ink);

        return match ($character) {
            'soft' => ['s' => "0 1px 3px rgb({$rgb} / 0.08)", 'm' => "0 6px 18px rgb({$rgb} / 0.1)", 'l' => "0 18px 48px rgb({$rgb} / 0.14)"],
            'hard' => ['s' => "3px 3px 0 {$ink}", 'm' => "6px 6px 0 {$ink}", 'l' => "10px 10px 0 {$ink}"],
            'layered' => [
                's' => "0 1px 1px rgb({$rgb} / 0.06), 0 2px 4px rgb({$rgb} / 0.06)",
                'm' => "0 1px 2px rgb({$rgb} / 0.06), 0 4px 8px rgb({$rgb} / 0.06), 0 12px 24px rgb({$rgb} / 0.08)",
                'l' => "0 2px 4px rgb({$rgb} / 0.05), 0 8px 16px rgb({$rgb} / 0.07), 0 24px 48px rgb({$rgb} / 0.12)",
            ],
            default => ['s' => 'none', 'm' => 'none', 'l' => 'none'],
        };
    }

    private static function rem(float $value): string
    {
        return self::number($value) . 'rem';
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
