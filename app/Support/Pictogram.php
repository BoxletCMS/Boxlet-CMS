<?php

namespace App\Support;

use App\Core\BlockDefinition;

/**
 * A SMALL DRAWING OF AN ARRANGEMENT (PLAN.md D-161, D-166): a strip 48 by 24, with marks
 * for a logo, lines for words, blocks for pictures and a pill for a button, placed as the
 * real thing places them. The header's and footer's arrangements are drawn with it on the
 * Appearance screen, and every block layout declares one in its block.php.
 *
 * SVG from attributes only — the admin's CSP refuses a style attribute — in the admin's
 * colours by class (admin-controls.css): a picture of an arrangement, not of the site.
 */
final class Pictogram
{
    /** The kinds of part a drawing is made of, each a class of its own. */
    public const PARTS = ['logo', 'line', 'image', 'button'];

    public const WIDTH = 48;
    public const HEIGHT = 24;

    /**
     * A drawing as a definition gives it — part => list of [x, y, width, height] — checked,
     * so a malformed one fails where the block is discovered rather than where it is drawn.
     *
     * @return array<string, list<array{int, int, int, int}>>
     */
    public static function validate(string $what, mixed $parts): array
    {
        if (!is_array($parts) || $parts === []) {
            BlockDefinition::fail($what, 'a pictogram is a map of part => rectangles');
        }
        $checked = [];
        foreach ($parts as $part => $rects) {
            if (!in_array($part, self::PARTS, true) || !is_array($rects) || $rects === [] || !array_is_list($rects)) {
                BlockDefinition::fail($what, 'pictogram parts are ' . implode(', ', self::PARTS) . ', each a list of rectangles');
            }
            foreach ($rects as $rect) {
                if (!is_array($rect) || count($rect) !== 4 || !array_is_list($rect)
                    || array_filter($rect, static fn (mixed $n): bool => !is_int($n) || $n < 0) !== []
                    || $rect[0] + $rect[2] > self::WIDTH || $rect[1] + $rect[3] > self::HEIGHT) {
                    BlockDefinition::fail($what, 'a rectangle is [x, y, width, height] inside 48 × 24');
                }
                $checked[$part][] = [$rect[0], $rect[1], $rect[2], $rect[3]];
            }
        }

        return $checked;
    }

    /**
     * The drawing as SVG, decorative: the words beside it say what it is.
     *
     * @param array<string, list<array{int, int, int, int}>> $parts
     */
    public static function svg(array $parts): string
    {
        $svg = '<svg viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '" focusable="false" aria-hidden="true"><rect class="pict-ground" width="' . self::WIDTH . '" height="' . self::HEIGHT . '" rx="3"/>';
        foreach ($parts as $part => $rects) {
            foreach ($rects as [$x, $y, $width, $height]) {
                $svg .= '<rect class="pict-' . e($part) . '" x="' . $x . '" y="' . $y . '" width="' . $width . '" height="' . $height . '" rx="' . ($part === 'button' ? '2' : '1') . '"/>';
            }
        }

        return $svg . '</svg>';
    }
}
