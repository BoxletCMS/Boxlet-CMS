<?php

namespace App\Modules\Appearance;


/**
 * EVERY WIDTH ON ONE PICTURE (PLAN.md D-157): the window, the sheet a boxed page sits on,
 * the column the text runs in, and how far the header and footer reach and what inside them
 * lines up with what.
 *
 * The five widths used to be spread over four tabs, and the only way to see how they related
 * was the preview — which shows the result, not the parts. This draws the parts, at the
 * scale of a 1440px window, from the decisions themselves.
 *
 * THE GEOMETRY EXISTS ONCE, HERE. The screen draws it as SVG on the server, and /check sends
 * the same rectangles back as numbers while a control moves, so the script only writes
 * attributes and never works out a width of its own (D-066's rule for the specimen, kept).
 * SVG attributes rather than styles, for the admin's CSP; the colours are the admin's, in
 * admin-appearance-layout.css.
 */
final class LayoutDiagram
{
    /** The window the picture is of, in CSS pixels, and how much smaller it is drawn. */
    private const WINDOW = 1440;
    private const SCALE = 5;
    private const WIDTH = 288;
    private const HEIGHT = 156;
    /** How tall a bar is drawn, and the room between parts, in drawing units. */
    private const BAR = 16;
    private const GAP = 6;
    /** The room above and below the sheet is drawn true up to this much, then capped. */
    private const MOST_ROOM = 16;

    /**
     * The rectangles, in the drawing's own units: x, y, width, height for each part.
     *
     * @param array<string, string> $decisions validated decisions
     * @return array<string, array{x: int, y: int, width: int, height: int}>
     */
    public static function geometry(array $decisions): array
    {
        $boxed = ($decisions['boxed'] ?? 'no') === 'yes';
        $unit = (float) ($decisions['spacing'] ?? 1);
        // The side margin is rem of its own since D-164.
        $frame = $boxed ? (float) ($decisions['frame'] ?? 3) * 16 : 0.0;
        $room = $boxed ? min(self::MOST_ROOM, $unit * (float) ($decisions['sheet_gap'] ?? 0) * 16 / self::SCALE) : 0.0;
        // The sheet is its own width, centred, never wider than the window less its margins.
        $sheetWidth = $boxed ? min((float) ($decisions['sheet_width'] ?? 72) * 16, self::WINDOW - 2 * $frame) / self::SCALE : self::WIDTH;
        $text = (float) ($decisions['container'] ?? 60) * 16 / self::SCALE;

        $headerOut = $boxed && ($decisions['header_bleed'] ?? 'sheet') === 'full';
        $footerOut = $boxed && ($decisions['footer_bleed'] ?? 'sheet') === 'full';
        // A bar outside the sheet stands across the window, above or below it, and the sheet
        // starts after it and the room around it.
        $sheetTop = $headerOut ? self::BAR + $room : $room;
        $sheetBottom = self::HEIGHT - ($footerOut ? self::BAR + $room : $room);
        $sheet = self::centred($sheetWidth, $sheetTop, $sheetBottom - $sheetTop);

        $header = $headerOut ? self::centred(self::WIDTH, 0, self::BAR) : self::centred($sheetWidth, $sheetTop, self::BAR);
        $footer = $footerOut ? self::centred(self::WIDTH, self::HEIGHT - self::BAR, self::BAR) : self::centred($sheetWidth, $sheetBottom - self::BAR, self::BAR);
        $inside = static fn (string $width, array $bar): float => min($bar['width'], match ($width) {
            'window' => (float) self::WIDTH,
            // In line with the box: the sheet, which unboxed is the window.
            'sheet' => $sheetWidth,
            default => min($text, $sheetWidth),
        });

        $contentTop = ($headerOut ? $sheetTop : $header['y'] + self::BAR) + self::GAP;
        $contentBottom = ($footerOut ? $sheetBottom : $footer['y']) - self::GAP;

        return [
            'window' => self::centred(self::WIDTH, 0, self::HEIGHT),
            'sheet' => $sheet,
            'content' => self::centred(min($text, $sheetWidth), $contentTop, max(0, $contentBottom - $contentTop)),
            'header' => $header,
            'header-content' => self::centred($inside($decisions['header_width'] ?? 'content', $header), $header['y'] + 6, 4),
            'footer' => $footer,
            'footer-content' => self::centred($inside($decisions['footer_width'] ?? 'content', $footer), $footer['y'] + 6, 4),
        ];
    }

    /**
     * The picture, with each part named by data-diagram so an answer from /check can move it.
     *
     * @param array<string, string> $decisions
     */
    public static function svg(array $decisions): string
    {
        $html = '<svg class="layout-diagram" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '" role="img" aria-labelledby="layout-diagram-legend" focusable="false">';
        foreach (self::geometry($decisions) as $part => $box) {
            $html .= '<rect class="diagram-' . e($part) . '" data-diagram="' . e($part) . '"'
                . ' x="' . $box['x'] . '" y="' . $box['y'] . '" width="' . $box['width'] . '" height="' . $box['height'] . '"'
                . ($part === 'sheet' || $part === 'window' ? ' rx="2"' : '') . '/>';
        }

        return $html . '</svg>';
    }

    /** @return array{x: int, y: int, width: int, height: int} */
    private static function centred(float $width, float $y, float $height): array
    {
        $width = (int) round(max(0.0, $width));

        return ['x' => intdiv(self::WIDTH - $width, 2), 'y' => (int) round($y), 'width' => $width, 'height' => (int) round($height)];
    }
}
