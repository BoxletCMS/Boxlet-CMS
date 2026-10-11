<?php

namespace App\Modules\Design;

use App\Core\Blocks;
use App\Core\Db;
use Throwable;

/**
 * Where characters come from, and which of them can be used (PLAN.md D-152, D-155): the
 * files Boxlet ships, the files an owner drops into designs/custom/, and the sets imported in
 * the admin into design_characters.
 *
 * Split from Characters, which answers questions about the characters; this finds them, and
 * decides for each custom file and imported row whether it is one — read through
 * DesignSet::parse(), with a composition, under an id nothing before it has — and says why
 * when it is not. A file or a row that is refused is left out and named, never fatal.
 */
final class CharacterSources
{
    /**
     * Boxlet's own, read as they are: trusted, not validated, because validating asks for the
     * default character, which is one of them (tests hold them instead).
     *
     * @return array<string, array{source: string, set: array<string, mixed>}>
     */
    public static function core(): array
    {
        $found = [];
        foreach (Characters::CORE as $id) {
            $set = json_decode((string) @file_get_contents(dirname(__DIR__, 3) . '/designs/core/' . $id . '.json'), true);
            if (is_array($set)) {
                $found[$id] = ['source' => 'core', 'set' => $set];
            }
        }

        return $found;
    }

    /**
     * designs/custom/*.json, by file name. Read, never written (D-155).
     *
     * @return list<array{name: string, text: string}>
     */
    public static function files(string $directory): array
    {
        if ($directory === '' || !is_dir($directory)) {
            return [];
        }
        $files = glob($directory . '/*.json') ?: [];
        sort($files);

        return array_map(static fn (string $file): array => ['name' => basename($file), 'text' => (string) @file_get_contents($file)], $files);
    }

    /**
     * The imported sets, by slug. None where there is no table yet: before migration 0032,
     * during an install, in a script.
     *
     * @return list<array{name: string, text: string}>
     */
    public static function rows(Db $db): array
    {
        try {
            $rows = $db->all('SELECT slug, set_json FROM design_characters ORDER BY slug');
        } catch (Throwable) {
            return [];
        }

        return array_values(array_map(static fn (array $row): array => ['name' => (string) $row['slug'], 'text' => (string) $row['set_json']], $rows));
    }

    /**
     * The usable characters among $items, sorted by name; the others go into $skipped with
     * the reason. A set without a composition is a design, not a character; an id already
     * in $taken belongs to what came before (core, then custom files, then imports).
     *
     * @param list<array{name: string, text: string}> $items
     * @param array<string, mixed> $taken id => anything, the characters already read
     * @param list<array{file: string, reason: string}> $skipped
     * @return array<string, array{source: string, set: array<string, mixed>}>
     */
    public static function read(array $items, string $source, Blocks $registry, array $taken, array &$skipped): array
    {
        $found = [];
        foreach ($items as $item) {
            $read = DesignSet::parse($item['text'], $registry);
            $set = $read['set'];
            if ($set === null) {
                $skipped[] = ['file' => $item['name'], 'reason' => implode(' ', $read['errors'])];
            } elseif ($set['composition'] === null) {
                $skipped[] = ['file' => $item['name'], 'reason' => t('characters.not_a_character')];
            } elseif (isset($taken[$set['id']]) || isset($found[$set['id']])) {
                $skipped[] = ['file' => $item['name'], 'reason' => t('characters.id_taken', ['id' => $set['id']])];
            } else {
                $found[$set['id']] = ['source' => $source, 'set' => $set];
            }
        }
        $name = static fn (array $entry): string => (string) ($entry['set']['name']['en'] ?? reset($entry['set']['name']));
        uasort($found, static fn (array $a, array $b): int => strcasecmp($name($a), $name($b)));

        return $found;
    }
}
