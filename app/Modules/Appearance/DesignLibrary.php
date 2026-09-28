<?php

namespace App\Modules\Appearance;

use App\Core\Db;
use App\Modules\Design\Presets;
use App\Modules\Design\Tokens;
use App\Modules\Settings\ChromeLook;

/**
 * The designs the owner keeps (PLAN.md D-061).
 *
 * Five characters could be loaded and nothing could be saved. This is the other half: a
 * whole look — the ten decisions and the seven header-and-footer choices — put away under a
 * name, brought back, written over, thrown away.
 *
 * WHAT IS STORED IS DECISIONS, NEVER DERIVED VALUES (SPEC §5.4). A saved design holds the
 * same shape design_tokens does, so bringing one back is loading a form, and every colour,
 * size and shadow is worked out from it exactly as it is for the site's own design.
 *
 * WHAT IS NOT STORED: the menu and the owner's words. Those are the site's content — a
 * design that carried them would put an English footer on a Croatian site the moment it was
 * used there.
 *
 * NOTHING HERE PUBLISHES. Bringing a design back fills the screen with it; the site changes
 * when Publish is pressed, which is the same confirmation a character has always needed.
 */
final class DesignLibrary
{
    /** A name has to fit the column and be something a person can tell apart from another. */
    public const MAX_NAME = 80;

    /**
     * Every saved design, newest change first, ready for the strip that lists them.
     *
     * @return list<array{id: int, name: string, character: string, decisions: array<string, string>, look: array<string, string>}>
     */
    public static function all(Db $db): array
    {
        $designs = [];
        foreach ($db->all('SELECT * FROM design_library ORDER BY updated_at DESC, id DESC') as $row) {
            $designs[] = self::shape($row);
        }

        return $designs;
    }

    /**
     * One saved design, or null. Its decisions are validated on the way out, so a row
     * written by an older version — or edited by hand — can never reach the screen as
     * something the design layer does not accept.
     *
     * @return array{id: int, name: string, character: string, decisions: array<string, string>, look: array<string, string>}|null
     */
    public static function find(Db $db, int $id): ?array
    {
        $row = $db->one('SELECT * FROM design_library WHERE id = ?', [$id]);

        return $row === null ? null : self::shape($row);
    }

    /**
     * Keeps a design under a name, writing over the one that already has that name.
     *
     * OVERWRITING IS THE POINT, not an accident to guard against: the owner adjusts a design
     * and keeps it under the same name, which is what "save" means everywhere else. Two rows
     * called "Autumn" would make the library ask a question it should never ask.
     *
     * @param array<string, string> $decisions
     * @param array<string, string> $look
     * @return int the row's id
     */
    public static function save(Db $db, string $name, array $decisions, array $look, string $character = ''): int
    {
        $name = self::cleanName($name);
        $now = gmdate('Y-m-d H:i:s');
        $existing = $db->one('SELECT id FROM design_library WHERE name = ?', [$name]);
        $decisionsJson = json_encode(Tokens::validate($decisions)['decisions'], JSON_THROW_ON_ERROR);
        $lookJson = json_encode(self::cleanLook($look), JSON_THROW_ON_ERROR);
        $character = Presets::exists($character) ? $character : '';

        if ($existing !== null) {
            $db->query(
                'UPDATE design_library SET decisions_json = ?, look_json = ?, character_name = ?, updated_at = ? WHERE id = ?',
                [$decisionsJson, $lookJson, $character, $now, (int) $existing['id']],
            );

            return (int) $existing['id'];
        }

        $db->query(
            'INSERT INTO design_library (name, character_name, decisions_json, look_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $character, $decisionsJson, $lookJson, $now, $now],
        );

        return (int) $db->lastInsertId();
    }

    /** Whether a design of this name is already kept — so "saved" and "written over" can be
     *  told apart in what the screen says afterwards. */
    public static function exists(Db $db, string $name): bool
    {
        return $db->one('SELECT id FROM design_library WHERE name = ?', [self::cleanName($name)]) !== null;
    }

    public static function delete(Db $db, int $id): void
    {
        $db->query('DELETE FROM design_library WHERE id = ?', [$id]);
    }

    /**
     * A name as it will be stored: trimmed, no control characters, and never empty — an
     * unnamed design in a list of designs cannot be told from the next one.
     */
    public static function cleanName(string $name): string
    {
        $name = trim(preg_replace('~[[:cntrl:]]+~u', ' ', $name) ?? $name);
        $name = trim(preg_replace('~\s+~u', ' ', $name) ?? $name);

        return mb_substr($name, 0, self::MAX_NAME);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, name: string, character: string, decisions: array<string, string>, look: array<string, string>}
     */
    private static function shape(array $row): array
    {
        $decisions = json_decode((string) $row['decisions_json'], true);
        $look = json_decode((string) $row['look_json'], true);

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'character' => (string) $row['character_name'],
            // Validated on the way out: a row from an older version is filled in with the
            // default preset's values rather than rendering as a broken screen.
            'decisions' => Tokens::validate(is_array($decisions) ? $decisions : [])['decisions'],
            'look' => self::cleanLook(is_array($look) ? $look : []),
        ];
    }

    /**
     * Every chrome choice, each from its own closed set, '' for "follow the character".
     * ChromeLook owns those sets; this only refuses what is not in them.
     *
     * @param array<mixed> $look
     * @return array<string, string>
     */
    private static function cleanLook(array $look): array
    {
        // A row kept before D-112 names the header's arrangement and behaviour as one
        // choice; modernise() reads it as this version's two, exactly as the settings are.
        return ChromeLook::modernise($look);
    }
}
