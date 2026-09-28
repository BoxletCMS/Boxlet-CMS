<?php

namespace App\Core;

use JsonException;

/**
 * The `settings` table: one JSON value per key (migration 0003).
 *
 * Written because six places had already grown their own copy of the same three lines —
 * Design twice, Composition twice, AdminView and the installer — and the settings screen
 * (PLAN.md D-028) adds several more. Six hand-rolled copies of one contract is how the
 * encoding flags in one of them quietly stop matching the others.
 *
 * In Core rather than in the settings module, because the installer and the admin shell
 * read settings and neither should depend on a feature module to do it.
 *
 * DELETE then INSERT, not an upsert: `INSERT ... ON DUPLICATE KEY UPDATE` is MySQL's and
 * `ON CONFLICT` is SQLite's, and SPEC §5.0 requires one statement that works on both.
 * That is also what the two existing writers do, so this changes no behaviour.
 */
final class Settings
{
    /**
     * A setting, or $default when it is not there or does not decode.
     *
     * The default is returned for a missing row AND for a row whose JSON is broken,
     * because a settings table edited by hand is a real thing on a shared host and a
     * screen that fatals on one bad row is worse than one that shows its default.
     */
    public static function get(Db $db, string $key, mixed $default = null): mixed
    {
        $row = $db->one('SELECT value_json FROM settings WHERE `key` = ?', [$key]);
        if ($row === null) {
            return $default;
        }

        try {
            return json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $default;
        }
    }

    /**
     * A setting as a string, which is what every caller of a name, a message or a time
     * zone actually wants. Anything stored that is not a string reads as $default.
     */
    public static function text(Db $db, string $key, string $default = ''): string
    {
        $value = self::get($db, $key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * A setting as a media id, or null. Zero and the empty string mean "none chosen",
     * which is what an unset picker posts.
     */
    public static function mediaId(Db $db, string $key): ?int
    {
        $value = self::get($db, $key);
        $id = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Stores one setting. UNESCAPED_UNICODE so a site name or a maintenance message with
     * Croatian letters is readable in the database rather than a row of š escapes.
     */
    public static function set(Db $db, string $key, mixed $value): void
    {
        $db->query('DELETE FROM settings WHERE `key` = ?', [$key]);
        $db->query(
            'INSERT INTO settings (`key`, value_json) VALUES (?, ?)',
            [$key, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );
    }

    /**
     * Several settings in one query, keyed as asked for, with $default for any that are
     * missing. One round trip for a screen that shows six of them.
     *
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public static function many(Db $db, array $keys, mixed $default = null): array
    {
        if ($keys === []) {
            return [];
        }

        $found = array_fill_keys($keys, $default);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        foreach ($db->all("SELECT `key`, value_json FROM settings WHERE `key` IN ({$placeholders})", $keys) as $row) {
            $key = (string) $row['key'];
            try {
                $found[$key] = json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $found[$key] = $default;
            }
        }

        return $found;
    }
}
