<?php

namespace App\Support;

/**
 * Which Boxlet this is, and which of two versions is the newer (PLAN.md D-140).
 *
 * The version is the VERSION file a release carries, written by tools/release/build.php.
 * A checkout has none, and is "development": never offered an update, since its code is
 * whatever was last pulled.
 */
final class Version
{
    public const DEVELOPMENT = 'development';

    private const PATTERN = '~^v(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.]+))?$~';

    public static function current(string $root): string
    {
        $file = $root . '/VERSION';
        $version = is_file($file) ? trim((string) file_get_contents($file)) : '';

        return self::valid($version) ? $version : self::DEVELOPMENT;
    }

    public static function valid(string $version): bool
    {
        return preg_match(self::PATTERN, $version) === 1;
    }

    /**
     * Below zero when $a is older than $b, zero when they are the same, above when newer.
     * Semver's order: numbers first, then a version with a pre-release part is older than
     * the same one without (v0.1.0-preview < v0.1.0), and pre-release parts compare piece by
     * piece, numbers as numbers (preview.2 < preview.10).
     */
    public static function compare(string $a, string $b): int
    {
        preg_match(self::PATTERN, $a, $x);
        preg_match(self::PATTERN, $b, $y);
        for ($i = 1; $i <= 3; $i++) {
            $difference = (int) ($x[$i] ?? 0) <=> (int) ($y[$i] ?? 0);
            if ($difference !== 0) {
                return $difference;
            }
        }
        $left = $x[4] ?? '';
        $right = $y[4] ?? '';
        if ($left === '' || $right === '') {
            // The one without a pre-release part is the release, and newer.
            return ($left === '' ? 1 : 0) - ($right === '' ? 1 : 0);
        }
        $leftParts = explode('.', $left);
        $rightParts = explode('.', $right);
        for ($i = 0; $i < max(count($leftParts), count($rightParts)); $i++) {
            if (!isset($leftParts[$i]) || !isset($rightParts[$i])) {
                return isset($leftParts[$i]) ? 1 : -1;
            }
            $l = $leftParts[$i];
            $r = $rightParts[$i];
            $difference = ctype_digit($l) && ctype_digit($r)
                ? (int) $l <=> (int) $r
                : (ctype_digit($l) ? -1 : (ctype_digit($r) ? 1 : strcmp($l, $r)));
            if ($difference !== 0) {
                return $difference <=> 0;
            }
        }

        return 0;
    }
}
