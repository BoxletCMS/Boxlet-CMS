<?php

namespace App\Modules\Update;

use App\Support\Version;
use RuntimeException;
use ZipArchive;

/**
 * A release package, checked before anything on the site moves (PLAN.md D-140).
 *
 * What a package must be: a ZIP whose every entry sits under boxlet/, with a VERSION newer
 * than the running one and the files a release is built with. What it must not carry: a path
 * that climbs out of its folder, a link, a settings file. The installer and the folders the
 * site keeps its own things in are in every release and are passed over when it is unpacked
 * (SKIPPED), not refused.
 */
final class Package
{
    /** What every release carries, as build.php's MUST_HAVE does. */
    private const REQUIRED = [
        'boxlet/VERSION',
        'boxlet/public/index.php',
        'boxlet/app/bootstrap.php',
        'boxlet/vendor/autoload.php',
        'boxlet/migrations/0001_migrations.sql',
    ];

    /**
     * Never written over the site: its settings, what it stores, its generated pictures and
     * cache, and the installer, which must not come back to a site that deleted it.
     */
    public const SKIPPED = ['storage/', 'public/m/', 'public/cache/', 'public/install.php', '.env'];

    private const MAX_ENTRIES = 30000;

    /**
     * The version inside the package at $path, once it has passed every check against a
     * site running $current. Throws with words the owner can act on otherwise.
     */
    public static function check(string $path, string $current): string
    {
        if ($current === Version::DEVELOPMENT) {
            throw new RuntimeException(t('updates.development'));
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException(t('updates.not_a_zip'));
        }
        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException(t('updates.not_a_release'));
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (!self::safe($name)) {
                    throw new RuntimeException(t('updates.unsafe', ['name' => $name]));
                }
                $zip->getExternalAttributesIndex($i, $system, $attributes);
                if ($system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException(t('updates.unsafe', ['name' => $name]));
                }
            }
            foreach (self::REQUIRED as $required) {
                if ($zip->locateName($required) === false) {
                    throw new RuntimeException(t('updates.not_a_release'));
                }
            }
            $version = trim((string) $zip->getFromName('boxlet/VERSION'));
        } finally {
            $zip->close();
        }

        if (!Version::valid($version)) {
            throw new RuntimeException(t('updates.not_a_release'));
        }
        if (Version::compare($version, $current) <= 0) {
            throw new RuntimeException(t('updates.not_newer', ['version' => $version, 'current' => $current]));
        }

        return $version;
    }

    /**
     * Where entry $name goes, relative to the site's root, or null for one that is not
     * written: a folder, or anything under SKIPPED.
     */
    public static function target(string $name): ?string
    {
        if (!str_starts_with($name, 'boxlet/') || str_ends_with($name, '/')) {
            return null;
        }
        $relative = substr($name, strlen('boxlet/'));
        foreach (self::SKIPPED as $skipped) {
            if ($relative === rtrim($skipped, '/') || (str_ends_with($skipped, '/') && str_starts_with($relative, $skipped))) {
                return null;
            }
        }

        return $relative === '' ? null : $relative;
    }

    /** Inside boxlet/, and nothing that climbs out of it or starts at the disk's root. */
    private static function safe(string $name): bool
    {
        return str_starts_with($name, 'boxlet/')
            && !str_contains($name, '\\')
            && !str_contains($name, "\0")
            && !str_contains('/' . $name . '/', '/../')
            && !str_contains('/' . $name . '/', '/./');
    }
}
