<?php

namespace App\Modules\Media;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\SectionStyle;

/**
 * A stored picture resolved for rendering, and the <picture> it becomes (SPEC §5.5).
 *
 * RESOLVED BEFORE RENDER, NEVER DURING. Blocks::render() is handed a lookup of id =>
 * picture, so a template never touches a database. A template that finds no entry draws
 * its placeholder exactly as it did before pictures existed — which is what keeps "never a
 * broken URL" true for a picture that was deleted, one whose variants are still being
 * made, and every caller that renders a block without a database at all (the block
 * library's previews, the design specimen).
 *
 * The <source> order is not decided here. MediaEncoder::FORMATS is 'best first, the
 * original format always last', and MediaVariants records them in that order, so the
 * stored list is already what <picture> needs: every format but the last becomes a
 * <source>, and the last is the <img> every browser understands.
 *
 * @phpstan-type Variant array{width: int, height: int, formats: list<string>}
 * @phpstan-type Picture array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, Variant>, alt: string, version: string}
 */
final class MediaPicture
{
    /**
     * The focal point is rounded to this many percent, because object-position travels as
     * a CLASS — nothing is inlined as a style attribute (SPEC §5.4). Two axes at 10% steps
     * is 22 class names in sections.css rather than the 121 a grid of pairs would need,
     * and 10% is finer than the difference anyone can pick out of a 200px thumbnail.
     */
    public const FOCAL_STEP = 10;

    /**
     * Every picture a page's blocks refer to, in one query rather than one per block.
     *
     * Both places a reference can live: a block's own media fields, and a section's
     * background picture (D-024).
     *
     * @param list<array{type?: string, content?: array<string, mixed>|null, style?: array<string, mixed>|null}> $blocks
     * @return array<int, Picture>
     */
    public static function forBlocks(Db $db, Blocks $registry, string $locale, array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';
            $content = is_array($block['content'] ?? null) ? $block['content'] : [];
            // Asked rather than repeated: this walked only the top level, as did the rule
            // on save, so a picture inside a repeater item rendered in the editor and was
            // missing from the page (O-11).
            foreach (MediaReference::idsIn($registry, $type, $content) as $id) {
                $ids[] = $id;
            }
            $style = is_array($block['style'] ?? null) ? $block['style'] : [];
            $surface = $style[SectionStyle::IMAGE] ?? null;
            if (is_int($surface)) {
                $ids[] = $surface;
            }
        }

        return self::resolve($db, $locale, $ids);
    }

    /**
     * @param list<int> $ids
     * @return array<int, Picture>
     */
    public static function resolve(Db $db, string $locale, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pictures = [];
        foreach ($db->all("SELECT * FROM media WHERE id IN ({$placeholders})", $ids) as $row) {
            $pictures[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'filename' => (string) $row['filename'],
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
                'focalX' => (int) $row['focal_x'],
                'focalY' => (int) $row['focal_y'],
                'variants' => MediaVariants::of($row),
                'alt' => '',
                'version' => MediaPresets::version((string) ($row['hash'] ?? ''), (int) ($row['revision'] ?? 0)),
            ];
        }

        // Alt text in the page's own language, else its fallback's (PLAN.md D-043): the
        // language's `fallback`, which a language added from the admin sets to the main
        // one, else the main language itself. For a picture that carries meaning an empty
        // alt in a translation is worse than the source language's words.
        //
        // Only a MISSING row falls back. A row whose alt is empty is a decision — this
        // picture is decoration, a screen reader passes over it — and is kept as it is.
        $fallback = (string) ($db->one(
            "SELECT COALESCE(l.fallback, p.code) AS code FROM locales l, locales p WHERE l.code = ? AND p.is_primary = 1",
            [$locale],
        )['code'] ?? '');
        $rows = $db->all(
            "SELECT media_id, locale, alt FROM media_meta WHERE locale IN (?, ?) AND media_id IN ({$placeholders})",
            array_merge([$locale, $fallback], $ids),
        );
        $own = [];
        foreach ($rows as $row) {
            $id = (int) $row['media_id'];
            if (!isset($pictures[$id]) || isset($own[$id])) {
                continue;
            }
            $pictures[$id]['alt'] = (string) $row['alt'];
            if ($row['locale'] === $locale) {
                $own[$id] = true;
            }
        }

        return $pictures;
    }

    /**
     * The classes that put the focal point where the crop should hold it.
     *
     * @param Picture $picture
     */
    public static function focalClasses(array $picture): string
    {
        $step = static fn (int $value): int => (int) (round(max(0, min(100, $value)) / self::FOCAL_STEP) * self::FOCAL_STEP);

        return 'focal-x-' . $step($picture['focalX']) . ' focal-y-' . $step($picture['focalY']);
    }

    /**
     * A <picture> for one stored picture, or '' when there is nothing to draw.
     *
     * Returns '' for a null picture and for one with no generated variant, so a caller can
     * write `$tag ?: $placeholder` and never emit a broken URL. An incomplete picture
     * renders whichever presets exist — the set is whatever generation reached before the
     * clock ran out, and the smallest is always made first (MediaVariants::ORDER).
     *
     * @param Picture|null $picture
     * @param list<string> $presets largest last: the last available one is what <img> points at
     */
    public static function tag(?array $picture, array $presets, string $sizes = '', bool $eager = false, string $class = ''): string
    {
        if ($picture === null) {
            return '';
        }

        $available = [];
        foreach ($presets as $preset) {
            $variant = $picture['variants'][$preset] ?? null;
            // A preset the picture has no entry for at all, which is every preset until
            // generation reaches it.
            if ($variant !== null && $variant['formats'] !== [] && $variant['width'] > 0) {
                $available[$preset] = $variant;
            }
        }
        if ($available === []) {
            return '';
        }

        $largestName = array_key_last($available);
        $largest = $available[$largestName];
        $formats = $largest['formats'];
        // The original format is always last (MediaEncoder::FORMATS); everything before it
        // is a <source> the browser may prefer.
        $original = (string) end($formats);

        $sources = '';
        foreach (array_slice($formats, 0, -1) as $format) {
            $sources .= '<source type="' . e(self::mime($format)) . '" srcset="'
                . e(self::srcset($picture, $available, $format)) . '"'
                . ($sizes === '' ? '' : ' sizes="' . e($sizes) . '"') . ">\n";
        }

        $classes = trim($class . ' ' . self::focalClasses($picture));

        return "<picture>\n" . $sources
            . '<img src="' . e(self::url($picture, $largestName, $original)) . '"'
            . ' srcset="' . e(self::srcset($picture, $available, $original)) . '"'
            . ($sizes === '' ? '' : ' sizes="' . e($sizes) . '"')
            . ' width="' . (int) $largest['width'] . '" height="' . (int) $largest['height'] . '"'
            . ' alt="' . e($picture['alt']) . '"'
            . ' class="' . e($classes) . '"'
            . ' decoding="async"'
            . ($eager ? '' : ' loading="lazy"')
            . ">\n</picture>\n";
    }

    /**
     * @param Picture $picture
     * @param array<string, Variant> $available
     */
    private static function srcset(array $picture, array $available, string $format): string
    {
        $entries = [];
        foreach ($available as $preset => $variant) {
            if (!in_array($format, $variant['formats'], true)) {
                continue;
            }
            $entries[] = self::url($picture, (string) $preset, $format) . ' ' . (int) $variant['width'] . 'w';
        }

        return implode(', ', $entries);
    }

    /**
     * @param Picture $picture
     */
    private static function url(array $picture, string $preset, string $format): string
    {
        return \App\Support\Url::asset(MediaPresets::file($preset, $picture['id'], $picture['filename'], $format)) . $picture['version'];
    }

    private static function mime(string $format): string
    {
        return 'image/' . ($format === 'jpg' ? 'jpeg' : $format);
    }
}
