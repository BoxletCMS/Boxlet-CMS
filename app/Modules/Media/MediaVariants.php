<?php

namespace App\Modules\Media;

use App\Core\Db;
use App\Support\Url;
use Throwable;

/**
 * Generating the variants of a picture, resumably (SPEC §5.5, PLAN.md D-019's sibling
 * problem: a shared host cuts a request off mid-work).
 *
 * Measured on this machine, a 2400×1600 photograph: one full-size AVIF takes 1272 ms
 * through Imagick, and the whole set of the five presets there then were, in three formats,
 * takes about 5.6 s.
 * A 30-second host survives one upload and not two at once. So generation does what it
 * can inside a budget the caller passes, records what it made, and leaves the row
 * incomplete for someone to finish.
 *
 * The budget is a parameter rather than something read from ini here, because
 * max_execution_time is 0 on this machine: read at the point of use, the whole mechanism
 * would be dead code in development and first exercised on a customer's server.
 *
 * Order is priority order. thumb and card are what the library and most pages show, so
 * they exist first even if nothing else does, and natural, which logos and uncropped
 * pictures draw from, right after them; full is last because it is the most expensive and
 * the least often needed.
 */
final class MediaVariants
{
    /** Cheapest and most needed first. */
    public const ORDER = ['thumb', 'card', 'natural', 'wide', 'hero', 'full'];

    /**
     * Where variants_json records the best-effort formats this picture cannot have.
     *
     * Not a preset name, so of() skips it: the key rides alongside the presets and never
     * reaches a <picture>.
     */
    public const UNAVAILABLE = '_unavailable';

    /**
     * Over this, an AVIF is written once more at RETRY_QUALITY (SPEC §8): the figure for a
     * `hero`, 1920×1080, and scaled by pixels above that for `full` (retryOver()).
     */
    private const RETRY_OVER = 200 * 1024;

    /** The picture RETRY_OVER is the budget for: `hero`'s 1920×1080. */
    private const RETRY_PIXELS = 1920 * 1080;

    private const RETRY_QUALITY = 40;

    /** What one encode might take, when deciding whether to start another. */
    private const RESERVE_SECONDS = 2.0;

    public function __construct(
        private readonly Db $db,
        private readonly MediaEncoder $encoder,
        private readonly MediaWriter $writer,
        private readonly string $storagePath,
        private readonly string $publicPath,
    ) {
    }

    /**
     * Generates what is missing, stopping cleanly when the budget runs out.
     *
     * @param float|null $budgetSeconds how long this call may spend; null for no limit
     * @return array{made: list<string>, complete: bool}
     */
    public function generate(int $mediaId, ?float $budgetSeconds = null): array
    {
        $media = $this->db->one('SELECT * FROM media WHERE id = ?', [$mediaId]);
        if ($media === null) {
            return ['made' => [], 'complete' => false];
        }

        $source = $this->storagePath . '/' . (string) $media['path'];
        $orientation = MediaEncoder::orientationOf($source);
        $formats = $this->encoder->formatsFor(self::extensionOf((string) $media['path']));
        $existing = self::decode($media['variants_json'] ?? null);
        // Formats this picture has already proved it cannot have. Not retried: the delegate
        // that failed last time fails again, and retrying turns every attempt to finish the
        // picture into another failure that leaves it unfinished.
        $unavailable = array_values(array_filter(
            is_array($existing[self::UNAVAILABLE] ?? null) ? $existing[self::UNAVAILABLE] : [],
            'is_string',
        ));
        $started = microtime(true);
        $made = [];

        foreach (self::ORDER as $preset) {
            $want = array_values(array_diff($formats, $unavailable));
            $have = $existing[$preset]['formats'] ?? [];
            $missing = array_values(array_diff($want, is_array($have) ? $have : []));
            if ($missing === []) {
                continue;
            }

            foreach ($missing as $format) {
                // Asked BEFORE starting, never during: a check inside an encode cannot
                // stop one, and the point is to stop before the wall rather than at it.
                if ($budgetSeconds !== null && (microtime(true) - $started) + self::RESERVE_SECONDS > $budgetSeconds) {
                    $this->record($mediaId, $existing, false);

                    return ['made' => $made, 'complete' => false];
                }

                $crop = MediaPresets::crop(
                    $preset,
                    (int) $media['width'],
                    (int) $media['height'],
                    (int) $media['focal_x'],
                    (int) $media['focal_y'],
                );
                $relative = MediaPresets::file($preset, $mediaId, (string) $media['filename'], $format);

                try {
                    $result = $this->writer->encode($source, $this->publicPath . '/' . $relative, $crop, $format, $orientation);
                } catch (Throwable) {
                    // RULE CHANGED, DELIBERATELY. This used to leave the row incomplete so
                    // the attempt was "not silently forgotten". But SPEC §5.5 says AVIF is
                    // best-effort and must never block, and incomplete is not a resting
                    // state: the library offers to finish the picture, finishing fails the
                    // same way, and it never completes. On a host whose AVIF delegate is
                    // declared but broken — GitHub's runners — every picture stayed
                    // unfinished for ever.
                    //
                    // So a best-effort format that fails is recorded as unavailable FOR THIS
                    // PICTURE and never attempted again, and the set completes on what is
                    // left. A failing FALLBACK format still leaves the row incomplete,
                    // because then there is genuinely nothing to serve.
                    //
                    // The partial file goes too: a failed encode can leave bytes on disk
                    // that variants_json never mentions, and nothing would ever remove them.
                    @unlink($this->publicPath . '/' . $relative);
                    if (in_array($format, MediaEncoder::FORMATS, true)) {
                        $unavailable[] = $format;
                        $existing[self::UNAVAILABLE] = array_values(array_unique($unavailable));
                    }
                    continue;
                }

                $result = $this->smallerAvif($preset, $format, $source, $relative, $crop, $orientation, $result);

                $existing[$preset]['formats'] = array_values(array_unique(
                    array_merge(is_array($have) ? $have : [], [$format]),
                ));
                $existing[$preset]['width'] = $result['width'];
                $existing[$preset]['height'] = $result['height'];
                $have = $existing[$preset]['formats'];
                $made[] = $preset . '.' . $format;
            }
        }

        $complete = self::isComplete($existing, array_values(array_diff($formats, $unavailable)));
        $this->record($mediaId, $existing, $complete);

        return ['made' => $made, 'complete' => $complete];
    }

    /**
     * One more attempt at an AVIF that came out too heavy, at a lower quality.
     *
     * The 200 KB in SPEC §8 is a budget for a typical photograph, not a contract: over the
     * ten demo photographs at `hero`, nine landed between 18 KB and 83 KB and one — a flat
     * wood-plank texture, which is the worst case for any codec — came out at 234 KB. A
     * site should not ship that silently, so anything over 200 KB, the figure §8 names, is
     * written once more at quality 40 and the smaller file wins.
     *
     * On ImageMagick this did nothing until 2026-09-19: the quality went through
     * setImageCompressionQuality, which the AVIF writer on 6.9.12 ignores, so the retry
     * produced an identical file. MediaWriter now sets the wand's quality too, which it
     * honours, and the retry works on both drivers. Measured on §8's 4 MB photograph at
     * `hero`: 279 KB at the default, 139 KB on the retry (PLAN.md O-18).
     *
     * ONCE, never a loop, and only where it applies: AVIF, because it is already the
     * smallest of the three and the one the page serves.
     *
     * `full` WAS LEFT OUT until 2026-09-26, as the largest public version by design, and
     * that stopped holding when a cover hero (D-118) put it on screen: a 1920 window at 2x
     * asks for 3840 pixels and gets `full`. §8's photograph came out at 707 KB there, and
     * at 415 KB at quality 40 with no difference to see side by side (O-35). It is judged
     * against a budget scaled by its pixels, retryOver(), since 200 KB for 2400×1590 would
     * push the finest photographs down where the grain goes soft. Ordinary photographs in
     * the library stayed at 25 to 140 KB at `full`, under that budget and untouched.
     *
     * The retry is written beside the target and moved over it only when it wins. Writing
     * straight over would mean a second pass that came out LARGER had destroyed the better
     * file — and a lower quality setting does not guarantee a smaller file on every codec
     * build. A retry that throws leaves the first result alone: AVIF is best-effort (§5.5)
     * and must never block the set from completing.
     *
     * @param array{x: int, y: int, width: int, height: int, targetWidth: int, targetHeight: int} $crop
     * @param array{width: int, height: int, bytes: int} $result
     * @return array{width: int, height: int, bytes: int}
     */
    public function smallerAvif(
        string $preset,
        string $format,
        string $source,
        string $relative,
        array $crop,
        int $orientation,
        array $result,
    ): array {
        if ($format !== 'avif' || $result['bytes'] <= self::retryOver($preset, $result['width'], $result['height'])) {
            return $result;
        }

        $target = $this->publicPath . '/' . $relative;
        $candidate = $target . '.retry';

        try {
            $retry = $this->writer->encode($source, $candidate, $crop, $format, $orientation, self::RETRY_QUALITY);
        } catch (Throwable) {
            @unlink($candidate);

            return $result;
        }

        if ($retry['bytes'] > 0 && $retry['bytes'] < $result['bytes'] && @rename($candidate, $target)) {
            return $retry;
        }
        @unlink($candidate);

        return $result;
    }

    /**
     * The size over which an AVIF of $preset, written at $width × $height, gets its second
     * attempt: RETRY_OVER for every cropped preset, and for `full`, whose size follows the
     * photograph, RETRY_OVER for each `hero` of pixels it holds — never less.
     */
    public static function retryOver(string $preset, int $width, int $height): int
    {
        if ((MediaPresets::ALL[$preset]['height'] ?? 0) !== 0) {
            return self::RETRY_OVER;
        }

        return max(self::RETRY_OVER, (int) round(self::RETRY_OVER * $width * $height / self::RETRY_PIXELS));
    }

    /**
     * The URL of one preset of a picture, or null when that preset has not been made.
     *
     * Media URLs use named presets only (SPEC §6), so this is the single place that turns
     * a media row into a URL. It belongs here rather than on a controller because nothing
     * that needs it is an HTTP concern: the library screen, one picture's screen, and the
     * list of pictures a field may choose from. Having the list reach up into a controller
     * for it put the module's layering the wrong way round.
     *
     * WebP first, because every browser that can run this admin reads it, then whatever
     * the original format was — which is always written.
     *
     * @param array<string, mixed> $media a media row
     */
    public static function url(array $media, string $preset): ?string
    {
        $formats = self::of($media)[$preset]['formats'] ?? [];
        if ($formats === []) {
            return null;
        }

        return Url::asset(MediaPresets::file(
            $preset,
            (int) $media['id'],
            (string) $media['filename'],
            in_array('webp', $formats, true) ? 'webp' : $formats[0],
        )) . MediaPresets::version((string) ($media['hash'] ?? ''), (int) ($media['revision'] ?? 0));
    }

    /**
     * The pictures still waiting, oldest first: the library finishes them one at a time.
     *
     * @return list<int>
     */
    public function incomplete(int $limit = 10): array
    {
        $rows = $this->db->all(
            "SELECT id FROM media WHERE status <> 'complete' ORDER BY id LIMIT {$limit}",
        );
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * What a picture has, for rendering: preset => width, height and formats.
     *
     * @param array<string, mixed> $media a media row
     * @return array<string, array{width: int, height: int, formats: list<string>}>
     */
    public static function of(array $media): array
    {
        $variants = [];
        foreach (self::decode($media['variants_json'] ?? null) as $preset => $variant) {
            if (!MediaPresets::exists((string) $preset) || !is_array($variant)) {
                continue;
            }
            $formats = $variant['formats'] ?? [];
            $variants[(string) $preset] = [
                'width' => (int) ($variant['width'] ?? 0),
                'height' => (int) ($variant['height'] ?? 0),
                'formats' => is_array($formats) ? array_values(array_map('strval', $formats)) : [],
            ];
        }

        return $variants;
    }

    /**
     * @param array<string, mixed> $variants
     * @param list<string>         $formats
     */
    private static function isComplete(array $variants, array $formats): bool
    {
        foreach (self::ORDER as $preset) {
            $have = $variants[$preset]['formats'] ?? [];
            if (!is_array($have) || array_diff($formats, $have) !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $variants
     */
    private function record(int $mediaId, array $variants, bool $complete): void
    {
        $this->db->query(
            'UPDATE media SET variants_json = ?, status = ? WHERE id = ?',
            [json_encode($variants, JSON_THROW_ON_ERROR), $complete ? 'complete' : 'incomplete', $mediaId],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(mixed $json): array
    {
        $decoded = is_string($json) && $json !== '' ? json_decode($json, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    private static function extensionOf(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }
}
