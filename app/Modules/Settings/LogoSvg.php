<?php

namespace App\Modules\Settings;

use App\Core\Db;
use App\Core\Settings;
use App\Support\Url;

/**
 * An SVG logo beside the picture ones (PLAN.md D-142): the site's, and the one for dark
 * surfaces. Kept apart from the media library on purpose, which goes on refusing SVG for
 * everything else: a logo is the one place a vector earns its risk, and the only way in is
 * through SvgSanitizer.
 *
 * Each is a file in public/m/logo/, named by its slot and a hash of what it holds, and a
 * setting holding that name and its size. The file is the cleaned SVG, never the upload.
 * Within its slot an SVG comes before a picture chosen from the library; removing it brings
 * the picture back.
 */
final class LogoSvg
{
    public const SLOTS = ['logo', 'logo_dark'];

    private const NAME = '~^(logo|logo_dark)-[0-9a-f]{12}\.svg$~';

    /**
     * Both slots, each with its address and size, or null when it has none.
     *
     * @return array{logo: array{url: string, width: int, height: int}|null, logo_dark: array{url: string, width: int, height: int}|null}
     */
    public static function all(Db $db): array
    {
        return ['logo' => self::get($db, 'logo'), 'logo_dark' => self::get($db, 'logo_dark')];
    }

    /**
     * @return array{url: string, width: int, height: int}|null
     */
    public static function get(Db $db, string $slot): ?array
    {
        $stored = self::stored($db, $slot);
        if ($stored === null) {
            return null;
        }

        return [
            'url' => Url::asset('m/logo/' . $stored['file']),
            'width' => max(1, (int) round($stored['width'])),
            'height' => max(1, (int) round($stored['height'])),
        ];
    }

    /**
     * Puts a cleaned SVG in $slot, in place of the one there, whose file goes when no other
     * slot holds the same.
     */
    public static function store(Db $db, string $publicPath, string $slot, string $svg, float $width, float $height): void
    {
        // Never a folder from an empty setting: that would be /m/logo at the root of the disk.
        if (trim($publicPath) === '') {
            throw new \RuntimeException(t('svg.cannot_write'));
        }
        $folder = rtrim($publicPath, '/') . '/m/logo';
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new \RuntimeException(t('svg.cannot_write'));
        }
        $file = $slot . '-' . substr(sha1($svg), 0, 12) . '.svg';
        if (file_put_contents($folder . '/' . $file . '.tmp', $svg) === false || !rename($folder . '/' . $file . '.tmp', $folder . '/' . $file)) {
            throw new \RuntimeException(t('svg.cannot_write'));
        }
        $old = self::stored($db, $slot);
        Settings::set($db, self::key($slot), ['file' => $file, 'width' => $width, 'height' => $height]);
        if ($old !== null && $old['file'] !== $file) {
            self::forget($db, $publicPath, $old['file']);
        }
    }

    /** Empties $slot, so the picture chosen for it, if any, is the logo again. */
    public static function remove(Db $db, string $publicPath, string $slot): void
    {
        $old = self::stored($db, $slot);
        Settings::set($db, self::key($slot), null);
        if ($old !== null) {
            self::forget($db, $publicPath, $old['file']);
        }
    }

    private static function key(string $slot): string
    {
        return 'site_' . $slot . '_svg';
    }

    /**
     * @return array{file: string, width: float, height: float}|null
     */
    private static function stored(Db $db, string $slot): ?array
    {
        if (!in_array($slot, self::SLOTS, true)) {
            return null;
        }
        $value = Settings::get($db, self::key($slot));
        if (!is_array($value) || !is_string($value['file'] ?? null) || preg_match(self::NAME, $value['file']) !== 1) {
            return null;
        }

        return ['file' => $value['file'], 'width' => (float) ($value['width'] ?? 0), 'height' => (float) ($value['height'] ?? 0)];
    }

    /** Removes $file unless a slot still holds it. */
    private static function forget(Db $db, string $publicPath, string $file): void
    {
        foreach (self::SLOTS as $slot) {
            if ((self::stored($db, $slot)['file'] ?? null) === $file) {
                return;
            }
        }
        $path = rtrim($publicPath, '/') . '/m/logo/' . $file;
        if (preg_match(self::NAME, $file) === 1 && is_file($path)) {
            unlink($path);
        }
    }
}
