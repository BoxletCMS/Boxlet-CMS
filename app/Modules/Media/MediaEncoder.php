<?php

namespace App\Modules\Media;

use Imagick;
use RuntimeException;
use Throwable;

/**
 * Turning an uploaded picture into the named variants (SPEC §5.5).
 *
 * Imagick when it is there, GD when it is not. Both are optional extensions, so Imagick
 * is referenced by name rather than as a class and every capability is probed rather
 * than assumed: a host with Imagick compiled without a WebP delegate is a real thing, and
 * it must refuse clearly instead of writing a file nobody can open.
 *
 * ORIENTATION IS APPLIED BEFORE CROPPING, then the EXIF is stripped. A photograph from a
 * phone is usually stored in the sensor's orientation with a tag saying how to turn it.
 * Cropping first would take the focal point from the unturned picture — the crop would
 * cut the wrong part of the photograph, and only for people who took it in portrait.
 * Stripping afterwards means the variant carries no location and no camera serial, and
 * needs no tag because the pixels are already the right way up.
 */
final class MediaEncoder
{
    /**
     * What a variant is written as, best first. The original format is always last.
     *
     * Public because these are also the BEST-EFFORT formats: SPEC §5.5 says AVIF must never
     * block, so MediaVariants needs to know which formats a picture is allowed to go without.
     */
    public const FORMATS = ['avif', 'webp'];

    /**
     * Proof, per driver and format, kept for the life of the process.
     *
     * @var array<string, bool>
     */
    private static array $proved = [];

    /**
     * A host that can process no images at all. Forced, because it cannot be asked for any
     * other way: null already means "probe", so without this the no-encoder path could only
     * be exercised on a machine that genuinely lacks both extensions — which is to say
     * never, on the machines where this is written.
     */
    public const NONE = 'none';

    /**
     * @param string|null $driver 'imagick' or 'gd' to force one, self::NONE to force none,
     *                            null to probe. Forcing is for tests and for measuring one
     *                            against the other; a site always probes.
     */
    public function __construct(private readonly ?string $driver = null)
    {
    }

    /**
     * 'imagick', 'gd', or none when the server can process no images at all.
     */
    public function driver(): ?string
    {
        if ($this->driver !== null) {
            return $this->driver === self::NONE ? null : $this->driver;
        }
        if (class_exists('Imagick')) {
            return 'imagick';
        }

        return extension_loaded('gd') ? 'gd' : null;
    }

    /**
     * Whether this server can write $format.
     *
     * PROVED BY ENCODING, not by asking. The old version took the extension's word for it —
     * Imagick::queryFormats('AVIF') listing AVIF, or GD defining imageavif() — and both can
     * be true on a host where the encode then fails or writes nothing. GitHub's runners are
     * exactly that host, and the docblock here claimed "probed, never assumed" while doing
     * the opposite: the whole test suite failed on it for a day.
     *
     * Only avif and webp are proved. A delegate that is declared and broken is a real thing
     * for those two; jpg, png and gif are part of the extension itself, and a build where
     * imagejpeg() exists but cannot write a JPEG is not a case worth paying for on every
     * call.
     *
     * Cached per process: this runs on every upload and the answer cannot change under us.
     */
    public function supports(string $format): bool
    {
        $driver = $this->driver();
        if ($driver === null) {
            return false;
        }

        $declared = $driver === 'imagick'
            ? class_exists('Imagick') && (new ('Imagick')())::queryFormats(strtoupper($format)) !== []
            : match ($format) {
                'avif' => function_exists('imageavif'),
                'webp' => function_exists('imagewebp'),
                'jpg', 'jpeg' => function_exists('imagejpeg'),
                'png' => function_exists('imagepng'),
                'gif' => function_exists('imagegif'),
                default => false,
            };

        if (!$declared || !in_array($format, self::FORMATS, true)) {
            return $declared;
        }

        $key = $driver . ':' . $format;
        if (isset(self::$proved[$key])) {
            return self::$proved[$key];
        }

        return self::$proved[$key] = $this->canReallyWrite($format);
    }

    /**
     * Encodes a two-pixel image and insists on getting bytes back.
     *
     * In memory rather than through a file: a temporary file would need somewhere writable
     * at the moment someone uploads, which is one more thing to go wrong in the middle of
     * answering the question "can this server do AVIF".
     */
    private function canReallyWrite(string $format): bool
    {
        try {
            if ($this->driver() === 'imagick') {
                $class = 'Imagick';
                $image = new $class();
                $image->newImage(2, 2, new ('ImagickPixel')('red'));
                $image->setImageFormat($format === 'jpg' ? 'jpeg' : $format);
                $bytes = (string) $image->getImageBlob();
                $image->clear();

                return $bytes !== '';
            }

            $image = imagecreatetruecolor(2, 2);
            ob_start();
            $written = match ($format) {
                'avif' => @imageavif($image),
                'webp' => @imagewebp($image),
                default => false,
            };
            $bytes = (string) ob_get_clean();
            imagedestroy($image);

            return $written && $bytes !== '';
        } catch (Throwable) {
            // A delegate that throws is a delegate that cannot write the format, which is
            // the whole question. Anything louder would turn a capability check into a
            // failed upload.
            return false;
        }
    }

    /**
     * The formats a variant will be written in: the best ones this server can produce,
     * plus the source's own format so there is always a fallback <picture> can use.
     *
     * @return list<string>
     */
    public function formatsFor(string $sourceExtension): array
    {
        $formats = [];
        foreach (self::FORMATS as $format) {
            if ($this->supports($format)) {
                $formats[] = $format;
            }
        }
        $fallback = $sourceExtension === 'jpeg' ? 'jpg' : $sourceExtension;
        if (!in_array($fallback, $formats, true) && $this->supports($fallback)) {
            $formats[] = $fallback;
        }

        return $formats;
    }

    /**
     * The EXIF orientation of a file, 1 when there is none or it cannot be read.
     *
     * Only JPEG and TIFF carry it. exif_read_data emits a warning for a file without it,
     * which is not an error worth surfacing: no tag means upright.
     */
    public static function orientationOf(string $file): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        $data = @exif_read_data($file);
        $orientation = is_array($data) ? ($data['Orientation'] ?? 1) : 1;

        return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /**
     * How an orientation turns a picture: degrees clockwise, and whether it is mirrored.
     *
     * The eight EXIF values are four rotations, each optionally flipped. Written out
     * rather than calculated because the mapping is a specification, not arithmetic, and
     * a clever expression here would be wrong in a way nobody could see.
     *
     * @return array{rotate: int, flip: bool}
     */
    public static function transformFor(int $orientation): array
    {
        return match ($orientation) {
            2 => ['rotate' => 0, 'flip' => true],
            3 => ['rotate' => 180, 'flip' => false],
            4 => ['rotate' => 180, 'flip' => true],
            5 => ['rotate' => 90, 'flip' => true],
            6 => ['rotate' => 90, 'flip' => false],
            7 => ['rotate' => 270, 'flip' => true],
            8 => ['rotate' => 270, 'flip' => false],
            default => ['rotate' => 0, 'flip' => false],
        };
    }

    /**
     * The size a picture presents at once its orientation is applied: a quarter turn
     * swaps width and height, which every later calculation depends on.
     *
     * @return array{int, int}
     */
    public static function orientedSize(int $width, int $height, int $orientation): array
    {
        return self::transformFor($orientation)['rotate'] % 180 === 90 ? [$height, $width] : [$width, $height];
    }

    /**
     * Reads a picture's true dimensions, with orientation already applied.
     *
     * @return array{width: int, height: int, mime: string, extension: string}
     */
    public function inspect(string $file): array
    {
        $size = @getimagesize($file);
        if ($size === false) {
            throw new RuntimeException('That file is not an image this server can read.');
        }
        [$width, $height] = $size;
        [$width, $height] = self::orientedSize((int) $width, (int) $height, self::orientationOf($file));

        $mime = (string) $size['mime'];

        return [
            'width' => $width,
            'height' => $height,
            'mime' => $mime,
            'extension' => match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/avif' => 'avif',
                default => '',
            },
        ];
    }
}
