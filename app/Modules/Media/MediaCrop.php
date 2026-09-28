<?php

namespace App\Modules\Media;

use RuntimeException;

/**
 * The geometry of cropping a picture by hand (PLAN.md D-026).
 *
 * WHAT THE BROWSER SENDS IS A RECTANGLE, NOT AN IMAGE. The dialog shows the `full`
 * variant, because originals are never public (D-020), so the rectangle arrives in that
 * variant's pixels and has to be scaled up to the original's before anything is cut. The
 * server then cuts the full-quality original and never trusts a pixel the browser drew.
 *
 * IT DOES NOT ROTATE. MediaWriter::encode() takes its rectangle in ORIENTED coordinates
 * and applies the EXIF turn before cropping, which is the same picture the person was
 * looking at when they dragged the box. Turning it here as well would turn it twice, and
 * only for photographs taken in portrait — the kind of fault that shows up on a customer's
 * phone and never on a developer's screen.
 *
 * The scale is a single number. `full` is the one preset that is not cropped: it takes the
 * whole source and only shrinks it to at most 2400 across. And media.width/height are
 * stored oriented, which is the same space the dialog works in, so there are not two
 * coordinate systems to confuse.
 */
final class MediaCrop
{
    /**
     * The shortest side a crop may have, in the ORIGINAL's pixels.
     *
     * Below this there is nothing left to make the larger presets from, and a picture that
     * silently becomes blurry everywhere it is used is worse than a refusal.
     */
    public const MIN_SIDE = 200;

    /**
     * How far a fixed-ratio crop may drift before it is refused.
     *
     * Not zero: the browser rounds to whole pixels and so does the scaling, so an exact
     * comparison would refuse crops that are correct. Two percent is far tighter than
     * anyone can drag by hand and far looser than rounding can produce.
     */
    private const RATIO_TOLERANCE = 0.02;

    /**
     * The shapes the dialog offers, named for the preset each one feeds. 0.0 is Free.
     *
     * `wide` is 1200/630, which is 1.9048. The label says 1.91 because that is what the
     * social networks call it; the number here is the preset's own, since this crop is
     * what that preset will be made from.
     */
    public const RATIOS = [
        'free' => 0.0,
        'hero' => 16 / 9,
        'card' => 3 / 2,
        'wide' => 1200 / 630,
        'thumb' => 1.0,
    ];

    /**
     * The rectangle in the original's pixels, or a refusal naming what was wrong.
     *
     * @param array<string, mixed> $media the media row
     * @param array{x: int, y: int, width: int, height: int, fullWidth: int, fullHeight: int} $sent
     * @return array{x: int, y: int, width: int, height: int}
     */
    public static function rectangle(array $media, array $sent, string $ratio): array
    {
        $sourceWidth = (int) ($media['width'] ?? 0);
        $sourceHeight = (int) ($media['height'] ?? 0);
        if ($sourceWidth < 1 || $sourceHeight < 1 || $sent['fullWidth'] < 1 || $sent['fullHeight'] < 1) {
            throw new RuntimeException(t('media.crop_outside'));
        }

        // One scale, from the width. The full variant keeps the source's proportions, so a
        // separate height scale would be the same number with more rounding in it.
        $scale = $sourceWidth / $sent['fullWidth'];
        $rect = [
            'x' => (int) round($sent['x'] * $scale),
            'y' => (int) round($sent['y'] * $scale),
            'width' => (int) round($sent['width'] * $scale),
            'height' => (int) round($sent['height'] * $scale),
        ];

        if ($rect['width'] < 1 || $rect['height'] < 1
            || $rect['x'] < 0 || $rect['y'] < 0
            || $rect['x'] + $rect['width'] > $sourceWidth
            || $rect['y'] + $rect['height'] > $sourceHeight) {
            throw new RuntimeException(t('media.crop_outside'));
        }

        if (min($rect['width'], $rect['height']) < self::MIN_SIDE) {
            throw new RuntimeException(t('media.crop_too_small', ['min' => (string) self::MIN_SIDE]));
        }

        $wanted = self::RATIOS[$ratio] ?? 0.0;
        if ($wanted > 0.0) {
            $actual = $rect['width'] / $rect['height'];
            if (abs($actual - $wanted) / $wanted > self::RATIO_TOLERANCE) {
                throw new RuntimeException(t('media.crop_ratio_wrong'));
            }
        }

        return $rect;
    }

    /**
     * Where the focal point lands after the crop, as percentages.
     *
     * It carries over when the point the owner chose is still in the picture, because that
     * is the subject they picked and cropping around it should not lose it. When the crop
     * cut it away there is nothing to carry, so it returns to the centre rather than
     * clinging to an edge — a focal point pinned to a border pushes every later crop
     * against that border.
     *
     * @param array<string, mixed> $media
     * @param array{x: int, y: int, width: int, height: int} $rect
     * @return array{x: int, y: int}
     */
    public static function focalAfter(array $media, array $rect): array
    {
        $sourceWidth = max(1, (int) ($media['width'] ?? 1));
        $sourceHeight = max(1, (int) ($media['height'] ?? 1));

        $pointX = (int) round($sourceWidth * max(0, min(100, (int) ($media['focal_x'] ?? 50))) / 100);
        $pointY = (int) round($sourceHeight * max(0, min(100, (int) ($media['focal_y'] ?? 50))) / 100);

        $inside = $pointX >= $rect['x'] && $pointX <= $rect['x'] + $rect['width']
            && $pointY >= $rect['y'] && $pointY <= $rect['y'] + $rect['height'];
        if (!$inside) {
            return ['x' => 50, 'y' => 50];
        }

        return [
            'x' => (int) round(($pointX - $rect['x']) / max(1, $rect['width']) * 100),
            'y' => (int) round(($pointY - $rect['y']) / max(1, $rect['height']) * 100),
        ];
    }

    /**
     * Cuts the original and returns the path of the cut file, in storage.
     *
     * Written outside the web root, like every original (D-020): a half-made crop must
     * never be reachable at a URL, and this one is temporary besides. The caller hands it
     * to MediaUpload, which sniffs it, gives it a name and moves it — so the ordinary
     * upload path does the storing, with its sha1 deduplication and its resumable
     * variants, and cropping does not become a second way to put a file in the library.
     *
     * targetWidth and targetHeight are the crop's own size: this is a cut at full quality,
     * not a resize. The presets are made from it afterwards, as they are for any picture.
     *
     * @param array{x: int, y: int, width: int, height: int} $rect
     */
    public static function cut(
        MediaWriter $writer,
        string $sourcePath,
        string $storagePath,
        array $rect,
        int $orientation,
        string $format,
    ): string {
        $temporary = tempnam($storagePath, 'crop');
        if ($temporary === false) {
            throw new RuntimeException(t('media.storage_unwritable'));
        }

        $writer->encode($sourcePath, $temporary, [
            'x' => $rect['x'],
            'y' => $rect['y'],
            'width' => $rect['width'],
            'height' => $rect['height'],
            'targetWidth' => $rect['width'],
            'targetHeight' => $rect['height'],
        ], $format, $orientation);

        return $temporary;
    }
}
