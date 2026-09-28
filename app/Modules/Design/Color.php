<?php

namespace App\Modules\Design;

/**
 * Colour maths for palette generation: hex and sRGB, OKLCH (perceptual lightness,
 * chroma and hue, so tints of one hue look evenly spaced) and WCAG 2 contrast ratios.
 */
final class Color
{
    public static function isHex(string $value): bool
    {
        return (bool) preg_match('~^#[0-9a-f]{6}$~', $value);
    }

    /**
     * #abc or #aabbcc in any case, as lower-case #aabbcc; null when it is not a colour.
     */
    public static function normalizeHex(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (preg_match('~^#([0-9a-f])([0-9a-f])([0-9a-f])$~', $value, $m)) {
            $value = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }

        return self::isHex($value) ? $value : null;
    }

    /**
     * WCAG 2 contrast ratio between two colours, from 1 to 21.
     */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * WCAG 2 relative luminance.
     */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::linearRgb($hex);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * @return array{float, float, float} OKLCH lightness 0–1, chroma, hue in degrees
     */
    public static function toOklch(string $hex): array
    {
        [$r, $g, $b] = self::linearRgb($hex);
        $l = self::cbrt(0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b);
        $m = self::cbrt(0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b);
        $s = self::cbrt(0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b);

        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
        $hue = rad2deg(atan2($bb, $a));

        return [$lightness, sqrt($a * $a + $bb * $bb), $hue < 0 ? $hue + 360 : $hue];
    }

    /**
     * The colour at OKLCH (lightness, chroma, hue), with chroma reduced until it fits
     * in sRGB, so lightness and hue are kept exactly.
     */
    public static function fromOklch(float $lightness, float $chroma, float $hue): string
    {
        $lightness = max(0.0, min(1.0, $lightness));
        $chroma = max(0.0, $chroma);
        while (true) {
            $rgb = self::oklabToRgb($lightness, $chroma * cos(deg2rad($hue)), $chroma * sin(deg2rad($hue)));
            if ($chroma <= 0.0 || self::inGamut($rgb)) {
                return self::hex($rgb);
            }
            $chroma = max(0.0, $chroma - 0.002);
        }
    }

    /**
     * "r g b" channels 0–255, for rgb(r g b / alpha) in shadows.
     */
    public static function channels(string $hex): string
    {
        return implode(' ', array_map(static fn (float $c): string => (string) (int) round($c * 255), self::rgb($hex)));
    }

    /**
     * @return array{float, float, float}
     */
    private static function rgb(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];
    }

    /**
     * @return array{float, float, float}
     */
    private static function linearRgb(string $hex): array
    {
        $linear = static fn (float $c): float => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = self::rgb($hex);

        return [$linear($r), $linear($g), $linear($b)];
    }

    /**
     * @return array{float, float, float} gamma-encoded sRGB, possibly out of range
     */
    private static function oklabToRgb(float $lightness, float $a, float $b): array
    {
        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;
        $gamma = static fn (float $c): float => $c <= 0.0031308 ? 12.92 * $c : 1.055 * $c ** (1 / 2.4) - 0.055;

        return [
            $gamma(4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s),
            $gamma(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s),
            $gamma(-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s),
        ];
    }

    /**
     * @param array{float, float, float} $rgb
     */
    private static function inGamut(array $rgb): bool
    {
        foreach ($rgb as $channel) {
            if ($channel < -0.0005 || $channel > 1.0005) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{float, float, float} $rgb
     */
    private static function hex(array $rgb): string
    {
        $hex = '#';
        foreach ($rgb as $channel) {
            $hex .= sprintf('%02x', (int) round(max(0.0, min(1.0, $channel)) * 255));
        }

        return $hex;
    }

    private static function cbrt(float $value): float
    {
        return $value < 0 ? -((-$value) ** (1 / 3)) : $value ** (1 / 3);
    }
}
