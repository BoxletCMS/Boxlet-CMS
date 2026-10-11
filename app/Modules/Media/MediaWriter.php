<?php

namespace App\Modules\Media;

use Imagick;
use RuntimeException;
use Throwable;

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
     * WHETHER IT IS OBEYED DEPENDS ON THE DELEGATE, and was not always (PLAN.md O-18). GD
     * honours it for all three. ImageMagick 6.9.12 honours it for JPEG, for AVIF only through
     * the wand's setter as well as the image's (both are set below), and never for WebP: so
     * where the encoder finds that, a WebP is still oriented, cropped and resized here and
     * then written by GD's imagewebp, which takes the quality it is given (D-209).
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

        [$width, $height] = $this->dimensions($target) ?? [$crop['targetWidth'], $crop['targetHeight']];

        return [
            'width' => $width,
            'height' => $height,
            'bytes' => (int) @filesize($target),
        ];
    }

    /**
     * A written file's size in pixels, read back from the file, or null when nothing can.
     *
     * getimagesize() first. PHP 8.1 recognises an AVIF but reports it as 0×0, which 8.2
     * fixed, and 8.1 is Boxlet's oldest PHP and a common one on shared hosting. It surfaced as
     * a red CI run on 8.1, where a 0×0 AVIF also made the `full` preset's retry budget the
     * flat one rather than the one scaled by pixels (D-130). So a 0 is no answer, and
     * Imagick, which wrote the file, reads it instead.
     *
     * @return array{int, int}|null
     */
    private function dimensions(string $target): ?array
    {
        $size = @getimagesize($target);
        if (is_array($size) && $size[0] > 0 && $size[1] > 0) {
            return [$size[0], $size[1]];
        }
        if ($this->encoder->driver() !== 'imagick') {
            return null;
        }
        try {
            $class = 'Imagick';
            $image = new $class();
            $image->pingImage($target);
            $found = [(int) $image->getImageWidth(), (int) $image->getImageHeight()];
            $image->clear();

            return $found[0] > 0 && $found[1] > 0 ? $found : null;
        } catch (Throwable) {
            return null;
        }
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
        $level = $quality ?? ($format === 'avif' ? 50 : 82);
        // WebP from an ImageMagick that writes it at one quality whatever it is told: the
        // pixels as Imagick made them, the encode GD's (O-18). PNG carries the alpha across.
        if ($format === 'webp' && function_exists('imagewebp') && !$this->encoder->imagickWebpQuality()) {
            $image->setImageFormat('png');
            $gd = imagecreatefromstring((string) $image->getImageBlob());
            $image->clear();
            if ($gd === false) {
                throw new RuntimeException('Writing webp failed.');
            }
            if (!imageistruecolor($gd)) {
                imagepalettetotruecolor($gd);
            }
            imagesavealpha($gd, true);
            $written = imagewebp($gd, $target, $level);
            imagedestroy($gd);
            if ($written === false) {
                throw new RuntimeException('Writing webp failed.');
            }

            return;
        }
        $image->setImageFormat($format === 'jpg' ? 'jpeg' : $format);
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
