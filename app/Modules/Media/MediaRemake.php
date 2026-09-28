<?php

namespace App\Modules\Media;

use App\Core\Db;
use Throwable;

/**
 * Making every picture's sizes again (PLAN.md O-13, D-048): after a preset changes, or to
 * give old pictures what a better encoder makes now — D-047's AVIF quality fix reaches only
 * pictures made after it until this runs.
 *
 * SAFE ON A LIVE SITE. Nothing is deleted first: each variant is written beside its file and
 * moved over it when whole, so a visitor gets the old file or the new one and never half of
 * one, and a picture being remade goes on showing everywhere. Once all of a picture's
 * variants are new its revision goes up, which changes their addresses (MediaPresets::
 * version) and so every browser's cache.
 *
 * RESUMABLE. Work is owed per picture in media.remake — the variants already made again,
 * NULL once done — and each step does what fits in the time it is given, so a pass cut
 * short by the request's time limit carries on where it stopped. The thin wrapper O-13
 * asked for: the encoding is MediaWriter's and the AVIF size rule MediaVariants', exactly
 * as on upload.
 */
final class MediaRemake
{
    private const RESERVE_SECONDS = 2.0;

    public function __construct(
        private readonly Db $db,
        private readonly MediaEncoder $encoder,
        private readonly MediaWriter $writer,
        private readonly MediaVariants $variants,
        private readonly string $storagePath,
        private readonly string $publicPath,
    ) {
    }

    /** Marks every complete picture as owed a remake; returns how many. */
    public function start(): int
    {
        // Pictures only: a file (D-126) has no sizes to make again.
        $this->db->query("UPDATE media SET remake = '' WHERE status = 'complete' AND kind = 'picture'");

        return $this->left();
    }

    /** How many pictures are still owed a remake. */
    public function left(): int
    {
        return (int) ($this->db->one('SELECT COUNT(*) AS n FROM media WHERE remake IS NOT NULL')['n'] ?? 0);
    }

    /**
     * ONE PICTURE MADE AGAIN NOW, because its focal point moved (PLAN.md D-121).
     *
     * The same safe pass as a remake of every picture: each file written beside the old one
     * and moved over it when whole, so the picture goes on showing everywhere while it is
     * made, and its addresses change once it is — which is what gets the new crop past a
     * browser's cache. The first version of this (before D-038) emptied the picture's
     * variants and made them again under the same addresses: for as long as that took, every
     * page showed a placeholder, and afterwards every browser that had seen it kept the old
     * crop.
     *
     * ONLY THE CROPPED SIZES. `natural` and `full` hold the whole picture wherever its point
     * is, so they are counted as made before the pass starts. What the budget does not allow
     * stays owed, and the Media screen finishes it (D-048).
     *
     * @return bool true when the picture is finished inside the budget
     */
    public function now(int $id, ?float $budgetSeconds): bool
    {
        $media = $this->db->one("SELECT * FROM media WHERE id = ? AND status = 'complete'", [$id]);
        if ($media === null) {
            // Not finished being made at all: generation reads the point as it goes, so
            // whatever it has still to make will already hold the new one.
            return false;
        }
        $whole = [];
        $formats = $this->encoder->formatsFor(strtolower(pathinfo((string) $media['path'], PATHINFO_EXTENSION)));
        foreach (MediaPresets::ALL as $preset => $size) {
            if ($size['height'] === 0) {
                foreach ($formats as $format) {
                    $whole[] = $preset . '.' . $format;
                }
            }
        }
        $this->db->query('UPDATE media SET remake = ? WHERE id = ?', [implode(',', $whole), $id]);
        $media['remake'] = implode(',', $whole);

        return $this->one($media, microtime(true), $budgetSeconds);
    }

    /**
     * Remakes what fits in $budgetSeconds, oldest picture first.
     *
     * @param float|null $budgetSeconds null for no limit
     * @return array{done: int, left: int}
     */
    public function step(?float $budgetSeconds): array
    {
        $started = microtime(true);
        $done = 0;
        foreach ($this->db->all('SELECT * FROM media WHERE remake IS NOT NULL ORDER BY id') as $media) {
            if (!$this->one($media, $started, $budgetSeconds)) {
                break;
            }
            $done++;
        }

        return ['done' => $done, 'left' => $this->left()];
    }

    /**
     * One picture's variants, each written whole and moved into place. Returns true when
     * the picture is finished, false when the time ran out first.
     *
     * @param array<string, mixed> $media
     */
    private function one(array $media, float $started, ?float $budgetSeconds): bool
    {
        $id = (int) $media['id'];
        $source = $this->storagePath . '/' . (string) $media['path'];
        if (!is_file($source)) {
            // Nothing to make them from. Left as they are, and not owed any more: a remake
            // that can never finish would hold every picture after it.
            $this->db->query('UPDATE media SET remake = NULL WHERE id = ?', [$id]);

            return true;
        }
        $done = array_filter(explode(',', (string) $media['remake']));
        $stored = json_decode((string) ($media['variants_json'] ?? ''), true);
        $variants = is_array($stored) ? $stored : [];
        $unavailable = is_array($variants[MediaVariants::UNAVAILABLE] ?? null) ? $variants[MediaVariants::UNAVAILABLE] : [];
        $formats = array_values(array_diff($this->encoder->formatsFor(strtolower(pathinfo((string) $media['path'], PATHINFO_EXTENSION))), $unavailable));
        $orientation = MediaEncoder::orientationOf($source);

        foreach (MediaVariants::ORDER as $preset) {
            foreach ($formats as $format) {
                $name = $preset . '.' . $format;
                if (in_array($name, $done, true)) {
                    continue;
                }
                if ($budgetSeconds !== null && (microtime(true) - $started) + self::RESERVE_SECONDS > $budgetSeconds) {
                    return false;
                }
                $crop = MediaPresets::crop($preset, (int) $media['width'], (int) $media['height'], (int) $media['focal_x'], (int) $media['focal_y']);
                $relative = MediaPresets::file($preset, $id, (string) $media['filename'], $format);
                $working = $relative . '.remake';
                try {
                    $result = $this->writer->encode($source, $this->publicPath . '/' . $working, $crop, $format, $orientation);
                    $result = $this->variants->smallerAvif($preset, $format, $source, $working, $crop, $orientation, $result);
                    if (!@rename($this->publicPath . '/' . $working, $this->publicPath . '/' . $relative)) {
                        throw new \RuntimeException('could not move ' . $working);
                    }
                    $variants[$preset]['formats'] = array_values(array_unique(array_merge($variants[$preset]['formats'] ?? [], [$format])));
                    $variants[$preset]['width'] = $result['width'];
                    $variants[$preset]['height'] = $result['height'];
                } catch (Throwable) {
                    // The old file stays where it was, serving; only the working copy goes.
                    @unlink($this->publicPath . '/' . $working);
                }
                $done[] = $name;
                $this->db->query(
                    'UPDATE media SET remake = ?, variants_json = ? WHERE id = ?',
                    [implode(',', $done), json_encode($variants, JSON_THROW_ON_ERROR), $id],
                );
            }
        }
        $this->db->query('UPDATE media SET remake = NULL, revision = revision + 1 WHERE id = ?', [$id]);

        return true;
    }
}
