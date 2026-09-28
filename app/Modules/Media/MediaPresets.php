<?php

namespace App\Modules\Media;

/**
 * The named sizes a picture is available in (SPEC §5.5), and the arithmetic that decides
 * what part of a source each one takes.
 *
 * Named presets only, never dimensions from a URL: free parameters would turn resizing
 * into a way to fill a disk. Variants are generated on upload, so these names are also
 * the complete list of files that will exist for every picture.
 */
final class MediaPresets
{
    /** A height of 0 means "keep the proportions", bounded by width. */
    public const ALL = [
        'thumb' => ['width' => 200, 'height' => 200],
        'card' => ['width' => 600, 'height' => 400],
        /* THE ONE SMALL SIZE THAT KEEPS THE PICTURE'S OWN SHAPE (PLAN.md D-119). Every other
           preset below `full` is cropped to a proportion, so a wide logo came out as its
           middle (320×69 to 69×69 and 103×69, measured) and a picture set to "natural" came
           out 3:2 whatever it was. `full` keeps the shape but runs to 2400 px, which is a
           lot to send for a mark 160 px wide. */
        'natural' => ['width' => 960, 'height' => 0],
        'wide' => ['width' => 1200, 'height' => 630],
        'hero' => ['width' => 1920, 'height' => 1080],
        'full' => ['width' => 2400, 'height' => 0],
    ];

    public static function exists(string $name): bool
    {
        return isset(self::ALL[$name]);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * The rectangle to take from the source, and the size to draw it at.
     *
     * Two rules decide everything here:
     *
     * A cropped preset keeps the focal point in frame. The crop is the largest rectangle
     * of the preset's proportions that fits the source, slid so that the focal point is
     * central, then pushed back inside the edges. That is what stops a crop cutting off
     * the head in a tall photograph of a person.
     *
     * Nothing is ever enlarged. A 200×200 thumb of a 120px picture is 120px, not a blurry
     * 200px. The output size is therefore not always the preset's size, which is why it is
     * returned: <picture> has to state the real width and height to avoid layout shift.
     *
     * @param int $focalX percentage across the source, 0–100
     * @param int $focalY percentage down the source, 0–100
     * @return array{x: int, y: int, width: int, height: int, targetWidth: int, targetHeight: int}
     */
    public static function crop(string $name, int $sourceWidth, int $sourceHeight, int $focalX = 50, int $focalY = 50): array
    {
        $preset = self::ALL[$name] ?? self::ALL['full'];
        $sourceWidth = max(1, $sourceWidth);
        $sourceHeight = max(1, $sourceHeight);

        if ($preset['height'] === 0) {
            return self::fit($preset['width'], $sourceWidth, $sourceHeight);
        }

        // The crop rectangle: the preset's proportions, as large as the source allows.
        $scale = min($sourceWidth / $preset['width'], $sourceHeight / $preset['height']);
        $cropWidth = min($sourceWidth, (int) round($preset['width'] * $scale));
        $cropHeight = min($sourceHeight, (int) round($preset['height'] * $scale));

        $x = self::offset($focalX, $sourceWidth, $cropWidth);
        $y = self::offset($focalY, $sourceHeight, $cropHeight);

        // Drawn at the preset's size unless the source is smaller, which never enlarges.
        $draw = min(1.0, $cropWidth / $preset['width']);

        return [
            'x' => $x,
            'y' => $y,
            'width' => $cropWidth,
            'height' => $cropHeight,
            'targetWidth' => max(1, (int) round($preset['width'] * $draw)),
            'targetHeight' => max(1, (int) round($preset['height'] * $draw)),
        ];
    }

    /**
     * The whole source, bounded by a maximum width and never enlarged.
     *
     * @return array{x: int, y: int, width: int, height: int, targetWidth: int, targetHeight: int}
     */
    private static function fit(int $maxWidth, int $sourceWidth, int $sourceHeight): array
    {
        $width = min($maxWidth, $sourceWidth);
        $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));

        return [
            'x' => 0,
            'y' => 0,
            'width' => $sourceWidth,
            'height' => $sourceHeight,
            'targetWidth' => $width,
            'targetHeight' => $height,
        ];
    }

    /**
     * Where a crop of $length starts so that the focal point sits in its middle, pushed
     * back inside the source when that would hang over an edge.
     */
    private static function offset(int $focal, int $source, int $length): int
    {
        $centre = $source * (max(0, min(100, $focal)) / 100);

        return (int) max(0, min($source - $length, round($centre - $length / 2)));
    }

    /**
     * Where a variant lives under public/, which is also its URL path: a real file, so
     * the web server answers it without PHP (SPEC §5.1).
     */
    public static function file(string $preset, int $mediaId, string $filename, string $extension): string
    {
        return 'm/' . $preset . '/' . $mediaId . '-' . $filename . '.' . $extension;
    }

    /**
     * What a variant's address carries after the file name so that a REPLACED picture is a
     * new address (the owner's report, 2026-09-19): replace() keeps the id and the name,
     * so every variant came back at the address the old one had, and browsers went on
     * showing the old picture from their cache — measured in the library, even after a
     * reload. The first eight characters of the original's hash, which replace() changes;
     * a query string, so the file on disk and its serving without PHP are untouched, the
     * way site.css is versioned (SPEC §5.4). A remake keeps the hash and changes the files,
     * so its revision is part of it too (D-048).
     */
    public static function version(string $hash, int $revision = 0): string
    {
        if ($hash === '') {
            return '';
        }

        return '?v=' . substr($hash, 0, 8) . ($revision > 0 ? '.' . $revision : '');
    }
}
