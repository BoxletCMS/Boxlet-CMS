<?php

namespace App\Modules\Design;

/**
 * What the decisions produce for the page around the content and for the header and the
 * footer: the sheet of a boxed page and its frame, how far the chrome's contents run, and the
 * chrome's own numbers (PLAN.md D-031, D-067, D-116, D-164). Split from Derived, which is the
 * content's type, space and shapes.
 */
final class DerivedPage
{
    /**
     * The page as a sheet (D-031). THE FRAME IS ZERO WHEN THE PAGE IS NOT BOXED: there is no
     * area around the sheet, so its colour paints nothing and no rule has to ask.
     *
     * @param array<string, string> $resolved
     * @param array<string, string> $colors
     * @return array<string, string>
     */
    public static function page(array $resolved, array $colors, string $sectionWidth = 'normal'): array
    {
        $boxed = $resolved['boxed'] === 'yes';
        $unit = (float) $resolved['spacing'];

        return [
            // A shade of the palette, or the owner's own colour where they gave one (D-076).
            'bg' => $resolved['page_background_colour'] !== ''
                ? $resolved['page_background_colour']
                : ($colors[$resolved['page_background']] ?? $colors['surface']),
            // The margin at the sides, in rem of its own since D-164.
            'frame' => $boxed ? Derived::rem((float) $resolved['frame']) : '0',
            // The room above and below the sheet, in spacing units (D-116).
            'frame-block' => $boxed ? Derived::rem($unit * (float) $resolved['sheet_gap']) : '0',
            'sheet-width' => $boxed ? Derived::rem((float) $resolved['sheet_width']) : 'none',
            'sheet-radius' => $boxed ? CssNumber::rem((float) $resolved['sheet_radius']) : '0',
            'sheet-shadow' => $boxed ? match ($resolved['sheet_shadow']) {
                'shadow' => Derived::shadows('soft', 40, Derived::shadowInk($colors))['l'],
                'hairline' => '0 0 0 1px ' . $colors['border'],
                default => 'none',
            } : 'none',
            'sheet' => $colors['background'],
            'header-width' => self::chromeWidth($resolved['header_width'], $resolved, $boxed, $sectionWidth),
            'footer-width' => self::chromeWidth($resolved['footer_width'], $resolved, $boxed, $sectionWidth),
            // The width the character's sections run to, whose edge is the page's: where a
            // narrow section set left begins (D-190).
            'content-width' => self::chromeWidth('content', $resolved, $boxed, $sectionWidth),
        ];
    }

    /**
     * The header's numbers (D-164) — its height, the logo's, how see-through and blurred a bar
     * that sticks or lies over the page is — and, where the owner gave one, the header's and
     * the footer's own colours with the ink worked out for them (D-076). A colour token that
     * is not here is the palette's shade: chrome.css reads each through var() with a fallback.
     *
     * @param array<string, string> $resolved
     * @param array<string, string> $colors
     * @return array<string, string>
     */
    public static function chrome(array $resolved, array $colors): array
    {
        $tokens = [
            'header-height' => CssNumber::rem((float) $resolved['header_height']),
            'logo-size' => CssNumber::rem((float) $resolved['logo_size']),
            // Only a bar that moves with the page can be see-through; a static one is solid.
            'header-opacity' => $resolved['header_behaviour'] === 'static' ? '1' : CssNumber::of((float) $resolved['header_opacity'] / 100),
            'header-blur' => $resolved['header_behaviour'] === 'static' ? '0px' : CssNumber::of((float) $resolved['header_blur']) . 'px',
        ];
        foreach (Tokens::ownChrome($resolved) as $part => $surface) {
            $inks = PaletteInks::inksOn($surface, $colors);
            $tokens[$part . '-bg'] = $surface;
            $tokens[$part . '-text'] = $inks['text'];
            $tokens[$part . '-muted'] = $inks['muted'];
            $tokens[$part . '-raised'] = $inks['raised'];
        }

        return $tokens;
    }

    /**
     * How wide the header's or the footer's contents run (D-123): the content column, the
     * sheet (less the container's side padding, which would otherwise stand outside it), or
     * the bar they stand in.
     *
     * @param array<string, string> $resolved
     */
    private static function chromeWidth(string $choice, array $resolved, bool $boxed, string $sectionWidth): string
    {
        if ($choice === 'window') {
            return '100%';
        }
        // To the content: the measure the character's sections run to, so the header's and the
        // footer's contents start where the page's do — Bold composes its sections wide, and a
        // footer at the plain content width stood 64px in from them (owner's review of phase 1).
        if ($choice !== 'sheet') {
            $container = (float) $resolved['container'];

            return match ($sectionWidth) {
                'narrow' => Derived::rem($container * 2 / 3),
                'wide' => Derived::rem($container * 7 / 6),
                'full' => '100%',
                default => Derived::rem($container),
            };
        }

        return $boxed ? 'calc(' . Derived::rem((float) $resolved['sheet_width']) . ' - 2 * var(--space-l))' : '100%';
    }
}
