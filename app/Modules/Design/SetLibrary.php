<?php

namespace App\Modules\Design;

use App\Core\Blocks;

/**
 * THE LIBRARY (PLAN.md D-195, the owner): the design sets Boxlet ships beside its characters,
 * in designs/library/, which a site does not have until its owner adds one from Appearance.
 * The rest are characters from the start (Characters::CORE); a library set is a character
 * once added, an imported one, by the same way in as a file chosen in Import (D-152).
 *
 * Read from the disk as an import is, through DesignSet::parse(): a file that does not read,
 * or that is no character, is left out. Shipped with every release and replaced by an update
 * as designs/core is (Upgrade swaps designs/ child by child).
 *
 * @phpstan-import-type ParsedSet from DesignSet
 */
final class SetLibrary
{
    /** A set's file name is its id: what a request may name, and nothing else. */
    public const ID = '[a-z0-9][a-z0-9-]{0,31}';

    /**
     * Every set in the library that reads as a character, by name.
     *
     * @return array<string, ParsedSet> file id => the set
     */
    public static function all(Blocks $registry, ?string $directory = null): array
    {
        $found = [];
        foreach (glob(($directory ?? self::directory()) . '/*.json') ?: [] as $file) {
            $id = basename($file, '.json');
            $set = preg_match('~^' . self::ID . '$~', $id) === 1 ? self::read($file, $registry) : null;
            if ($set !== null) {
                $found[$id] = $set;
            }
        }
        $name = static fn (array $set): string => (string) ($set['name']['en'] ?? reset($set['name']));
        uasort($found, static fn (array $a, array $b): int => strcasecmp($name($a), $name($b)));

        return $found;
    }

    /**
     * One set by its id, or null for an id the library has no character under.
     *
     * @return ParsedSet|null
     */
    public static function find(string $id, Blocks $registry, ?string $directory = null): ?array
    {
        if (preg_match('~^' . self::ID . '$~', $id) !== 1) {
            return null;
        }
        $file = ($directory ?? self::directory()) . '/' . $id . '.json';

        return is_file($file) ? self::read($file, $registry) : null;
    }

    public static function directory(): string
    {
        return dirname(__DIR__, 3) . '/designs/library';
    }

    /**
     * @return ParsedSet|null
     */
    private static function read(string $file, Blocks $registry): ?array
    {
        $set = DesignSet::parse((string) file_get_contents($file, false, null, 0, DesignSet::MAX_BYTES + 1), $registry)['set'];

        return $set !== null && $set['composition'] !== null ? $set : null;
    }
}
