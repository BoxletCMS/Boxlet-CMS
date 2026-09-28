<?php

namespace App\Modules\Media;

use Imagick;
use RuntimeException;

/**
 * The pixel work: orient, crop, resize, strip, encode (SPEC §5.5).
 *
 * Split from MediaEncoder, which decides WHAT a picture needs — which driver is present,
 * which formats it can write, how an EXIF orientation turns it. Those are answerable
 * without touching a file and are tested that way. This one only ever moves pixels.
 *
 * Imagick when it is there, GD when it is not, with the same result either way: the
 * orientation applied BEFORE the crop, so the focal point means what the person who
 * clicked it meant, and the EXIF stripped afterwards, so no variant carries a location.
 */
final class MediaWriter
{
    public function __construct(private readonly MediaEncoder $encoder)
    {
    }

    /**
     * Writes one variant: orient, crop, resize, strip, encode.
     *
     * The crop rectangle comes from MediaPresets and is expressed in ORIENTED coordinates,
     * because that is the picture a person sees and the focal point they clicked on.
     *
     * $quality is the encoder's own scale, or null for this codec's default — avif 50,
     * webp and jpeg 82. It exists so a variant that came out too heavy can be written
     * once more at a lower setting (MediaVariants), and it is passed rather than stored
     * because the default is right for every picture but one in ten.
     *
     * WHETHER IT IS OBEYED DEPENDS ON THE DELEGATE, and the defaults above are not a
     * promise. GD honours it for all three. ImageMagick 6.9.12 honours it for JPEG and
     * silently ignores it for AVIF and WebP — so on such a host those two are written at
     * whatever the delegate's own default is, and the 50 and 82 never apply. Measured in
     * MediaVariants::smallerAvif().
     *
     * @param array{x: int, y: int, width: int, height: int, targetWidth: int, targetHeight: int} $crop
     * @return array{width: int, height: int, bytes: int}
     */
    public function encode(string $source, string $target, array $crop, string $format, int $orientation, ?int $quality = null): array
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create directory {$directory}");
        }
        if (!$this->encoder->supports($format)) {
            throw new RuntimeException("This server cannot write {$format}.");
        }

        $this->encoder->driver() === 'imagick'
            ? $this->encodeImagick($source, $target, $crop, $format, $orientation, $quality)
            : $this->encodeGd($source, $target, $crop, $format, $orientation, $quality);

        $size = @getimagesize($target);

        return [
            'width' => (int) ($size[0] ?? $crop['targetWidth']),
            'height' => (int) ($size[1] ?? $crop['targetHeight']),
            'bytes' => (int) @filesize($target),
        ];
    }

    /**
     * @param array{x: int, y: int, width: int, height: int, targetWidth: int, targetHeight: int} $crop
     */
    private function encodeImagick(string $source, string $target, array $crop, string $format, int $orientation, ?int $quality = null): void
    {
        $class = 'Imagick';
        /** @var Imagick $image */
        $image = new $class($source);

        $transform = MediaEncoder::transformFor($orientation);
        if ($transform['rotate'] !== 0) {
            $image->rotateImage(new ('ImagickPixel')('none'), $transform['rotate']);
        }
        if ($transform['flip']) {
            $image->flopImage();
        }

        $image->cropImage($crop['width'], $crop['height'], $crop['x'], $crop['y']);
        $image->resizeImage($crop['targetWidth'], $crop['targetHeight'], $class::FILTER_LANCZOS, 1);

        // Location, camera serial and the orientation tag all go: the pixels are already
        // the right way up, so a tag would turn them a second time.
        $image->stripImage();
        $image->setImageFormat($format === 'jpg' ? 'jpeg' : $format);
        $level = $quality ?? ($format === 'avif' ? 50 : 82);
        // BOTH setters. The image's own quality is what JPEG reads; the AVIF writer on
        // ImageMagick 6.9.12 reads the wand's instead and ignored the first alone — the
        // same 1920×1080 photograph came out at 285,806 B at every quality asked through
        // it, and at 49,086 B at quality 30 through this one (PLAN.md O-18).
        $image->setImageCompressionQuality($level);
        $image->setCompressionQuality($level);
        $image->writeImage($target);
        $image->clear();
    }

    /**
     * @param array{x: int, y: int, width: int, height: int, targetWidth: int, targetHeight: int} $crop
     */
    private function encodeGd(string $source, string $target, array $crop, string $format, int $orientation, ?int $quality = null): void
    {
        $size = @getimagesize($source);
        $image = match ((string) ($size['mime'] ?? '')) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            'image/gif' => @imagecreatefromgif($source),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($source) : false,
            default => false,
        };
        if ($image === false) {
            throw new RuntimeException('That file is not an image this server can read.');
        }

        $transform = MediaEncoder::transformFor($orientation);
        if ($transform['rotate'] !== 0) {
            // imagerotate turns anticlockwise; EXIF describes clockwise.
            $rotated = imagerotate($image, 360 - $transform['rotate'], 0);
            if ($rotated !== false) {
                imagedestroy($image);
                $image = $rotated;
            }
        }
        if ($transform['flip']) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        // At least one pixel each way. A crop can only reach zero through a corrupt row or
        // arithmetic nobody intended, and imagecreatetruecolor(0, …) fails in a way that
        // surfaces as a broken picture rather than an error.
        $out = imagecreatetruecolor(max(1, $crop['targetWidth']), max(1, $crop['targetHeight']));

        // PNG and WebP can be transparent; JPEG cannot, and a black background is what
        // an unfilled truecolor canvas gives. Allocation returns false when the palette
        // cannot take another colour, which is not something to paint with.
        if (in_array($format, ['png', 'webp', 'avif'], true)) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
            $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefill($out, 0, 0, $transparent);
            }
        } else {
            $white = imagecolorallocate($out, 255, 255, 255);
            if ($white !== false) {
                imagefill($out, 0, 0, $white);
            }
        }

        imagecopyresampled(
            $out,
            $image,
            0,
            0,
            $crop['x'],
            $crop['y'],
            $crop['targetWidth'],
            $crop['targetHeight'],
            $crop['width'],
            $crop['height'],
        );
        imagedestroy($image);

        // GD carries no EXIF into what it writes, so there is nothing to strip.
        // PNG's last argument is a compression LEVEL, not a quality, and GIF takes none,
        // so $quality reaches only the three codecs where it means what it says.
        $written = match ($format) {
            'avif' => imageavif($out, $target, $quality ?? 50),
            'webp' => imagewebp($out, $target, $quality ?? 82),
            'png' => imagepng($out, $target, 6),
            'gif' => imagegif($out, $target),
            default => imagejpeg($out, $target, $quality ?? 82),
        };
        imagedestroy($out);

        if ($written === false) {
            throw new RuntimeException("Writing {$format} failed.");
        }
    }

}
