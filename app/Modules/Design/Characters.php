<?php

namespace App\Modules\Design;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\Vocabulary\Decisions;
use Closure;

/**
 * Every character a site can choose, wherever it comes from (PLAN.md D-152): those
 * Boxlet ships in designs/core/, sets an owner dropped into designs/custom/ over FTP
 * (D-155), and sets imported in the admin, kept in design_characters (migration 0032).
 *
 * WHAT A CHARACTER IS lives in its file now, not in PHP: the decisions it makes, the
 * header and footer it gives, the composition it gives a page. This is the one reader of
 * all three, read once per request; Presets, Composition and ChromeLook ask it.
 *
 * CORE IS TRUSTED, NOT VALIDATED, AS IT LOADS. Tokens::validate() falls back to the default
 * character, so validating the characters on the way in would call itself. The core files
 * are held instead by a test: Tokens::validate() and DesignSet::parse() read each without an
 * error, a warning or a changed value (characters_test.php). Everything else is read
 * through DesignSet::parse(), and a file it refuses is left out — never the site.
 *
 * Precedence when two share an id: core, then custom, then imported. A custom file or an
 * imported set that cannot be used is noted in skipped(), which the Appearance screen shows
 * as a notice. An import never takes an id that is in use: addImported() gives it `-2`,
 * `-3`… so a second `soft` cannot replace the first, or the one Boxlet ships.
 *
 * @phpstan-import-type ParsedSet from DesignSet
 */
final class Characters
{
    /**
     * The characters Boxlet ships, in the order the screen shows them: the first five, then
     * the owner's sets taken into core (D-195), in the order the owner named them. The other
     * sets the owner made are in designs/library/, to add from Appearance (SetLibrary).
     */
    public const CORE = ['editorial', 'minimal', 'bold', 'soft', 'brutalist', 'terra', 'clinic', 'gallery', 'launch', 'commons', 'zine', 'couture', 'riso'];

    /** @var array<string, array{source: string, set: array<string, mixed>}>|null id => where it came from and what it is */
    private static ?array $all = null;
    /** @var list<array{file: string, reason: string}> */
    private static array $skipped = [];
    private static string $custom = '';
    private static ?Closure $registry = null;
    private static ?Closure $db = null;

    /**
     * Where custom files are, and how to reach the block registry and the database, set once
     * by the bootstrap. Without it — a script, a test that never booted — only the core
     * characters exist.
     *
     * @param Closure(): Blocks $registry
     * @param (Closure(): Db)|null $db
     */
    public static function use(string $customDirectory, Closure $registry, ?Closure $db = null): void
    {
        self::$custom = $customDirectory;
        self::$registry = $registry;
        self::$db = $db;
        self::reset();
    }

    /** Forgets what was read, so the next question reads again. */
    public static function reset(): void
    {
        self::$all = null;
        self::$skipped = [];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $id): bool
    {
        return isset(self::all()[$id]);
    }

    /** 'core', 'custom' or 'imported'; '' for an id that is no character. */
    public static function source(string $id): string
    {
        return self::all()[$id]['source'] ?? '';
    }

    /**
     * Every global decision of a character — its own, then what the vocabulary gives for a
     * key it leaves out — in the vocabulary's order, the header and footer included (D-164).
     * The default character's for an id that is none.
     *
     * @return array<string, string>
     */
    public static function decisions(string $id): array
    {
        $all = self::all();
        $set = ($all[$id] ?? $all[Presets::DEFAULT])['set'];
        $own = (is_array($set['decisions'] ?? null) ? $set['decisions'] : []) + (is_array($set['look'] ?? null) ? $set['look'] : []);
        $decisions = [];
        foreach (Decisions::neutral() as $key => $neutral) {
            $value = $own[$key] ?? '';
            $decisions[$key] = is_string($value) && $value !== '' ? $value : $neutral;
        }

        return $decisions;
    }

    /**
     * The decisions no character makes: what a set that leaves a key out means by it.
     *
     * @return array<string, string>
     */
    public static function neutral(): array
    {
        return Decisions::neutral();
    }

    /**
     * Layers 2 and 3 of a character: `section` is the style every section has where its
     * owner has set nothing (every key of SectionStyle::DEFAULTS, D-165), `surfaces` and
     * `dividers` what a section of one block type does differently, `layouts` the
     * arrangement of a type that offers several. The default character's for an id that is
     * none; Composition keeps its own answer for that case (SectionStyle::DEFAULTS), which
     * is what a site has always had.
     *
     * @return array{section: array<string, string>, surfaces: array<string, string>, dividers: array<string, string>, layouts: array<string, string>, options: array<string, array<string, string>>}
     */
    public static function composition(string $id): array
    {
        $all = self::all();

        return ($all[$id] ?? $all[Presets::DEFAULT])['set']['composition'];
    }

    /**
     * A character's dark version (D-185): the keys of Decisions::DARK it answers for dark mode,
     * none for a character that has no dark version.
     *
     * @return array<string, string>
     */
    public static function dark(string $id): array
    {
        $all = self::all();

        return ($all[$id] ?? $all[Presets::DEFAULT])['set']['dark'] ?? [];
    }

    /** @return list<string> a character's own words for what it is, a set's `tags` (D-200) */
    public static function tags(string $id): array
    {
        $all = self::all();

        return array_values(array_map('strval', ($all[$id] ?? $all[Presets::DEFAULT])['set']['tags'] ?? []));
    }

    /**
     * The starter sections a character offers (D-169), each with its words per language.
     *
     * @return list<array<string, mixed>>
     */
    public static function patterns(string $id): array
    {
        $all = self::all();

        return ($all[$id] ?? $all[Presets::DEFAULT])['set']['patterns'] ?? [];
    }

    /**
     * What a character gives the header and footer, every choice set: the look's part of its
     * decisions. The default character's for an id that is none.
     *
     * @return array<string, string>
     */
    public static function look(string $id): array
    {
        return array_intersect_key(self::decisions($id), array_flip(Decisions::keys('look')));
    }

    /**
     * A character's name as the admin shows it. Boxlet's own come from the lang files, as
     * they always have, so a translation of the admin carries them; a custom or imported one
     * from its own file, in the admin's language or English, then the lang key, then its id.
     */
    public static function label(string $id): string
    {
        return self::words($id, 'name', 'design.preset.' . $id);
    }

    /** The line under a character's name, found the same way as label(). */
    public static function hint(string $id): string
    {
        return self::words($id, 'description', 'design.preset.' . $id . '_hint');
    }

    /**
     * The custom files and imported sets left out, and why: not JSON, refused by validation,
     * no composition, or an id another character already has.
     *
     * @return list<array{file: string, reason: string}>
     */
    public static function skipped(): array
    {
        self::all();

        return self::$skipped;
    }

    private static function words(string $id, string $field, string $key): string
    {
        $entry = self::all()[$id] ?? null;
        $translated = t($key);
        $has = $translated !== $key;
        if ($entry === null || $entry['source'] === 'core') {
            return $has ? $translated : (string) ($entry['set'][$field]['en'] ?? $id);
        }
        $own = $entry['set'][$field];

        return (string) ($own['en'] ?? reset($own) ?: ($has ? $translated : ($field === 'name' ? $id : '')));
    }

    /**
     * @return array<string, array{source: string, set: array<string, mixed>}>
     */
    private static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        // Core first and on its own: reading a custom file validates it, validating asks for
        // the default character, and the default character must already be here to answer.
        self::$all = CharacterSources::core();
        if (self::$registry !== null) {
            $registry = (self::$registry)();
            $sources = [
                'custom' => CharacterSources::files(self::$custom),
                'imported' => self::$db !== null ? CharacterSources::rows((self::$db)()) : [],
            ];
            foreach ($sources as $source => $items) {
                self::$all += CharacterSources::read($items, $source, $registry, self::$all, self::$skipped);
            }
        }

        return self::$all;
    }

    /**
     * Keeps a set, as parsed, as an imported character, under an id no character has yet:
     * its own, or its own with `-2`, `-3`… The name is left as it is. Returns the id it was
     * kept under.
     *
     * @param ParsedSet $set
     */
    public static function addImported(Db $db, array $set, string $source = 'import'): string
    {
        $taken = array_flip(self::names());
        foreach ($db->all('SELECT slug FROM design_characters') as $row) {
            $taken[(string) $row['slug']] = true;
        }
        $slug = $set['id'];
        for ($n = 2; isset($taken[$slug]); $n++) {
            // Shortened to make room, so the id still fits character_name VARCHAR(32).
            $suffix = '-' . $n;
            $slug = rtrim(substr($set['id'], 0, 32 - strlen($suffix)), '-') . $suffix;
        }

        $now = gmdate('Y-m-d H:i:s');
        $db->query(
            'INSERT INTO design_characters (slug, set_json, source, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$slug, DesignSet::export($slug, $set['name'], $set['description'], $set['decisions'], $set['look'], $set['composition'], $set['author'], $set['patterns'], $set['dark'], $set['tags']), $source, $now, $now],
        );
        self::reset();

        return $slug;
    }

    /**
     * Removes an imported character. Only an imported one: a core character is Boxlet's and a
     * custom one is a file the owner put there (D-155). A site composed with it falls back to
     * the default character, as Composition::active() does for any id that is gone.
     */
    public static function deleteImported(Db $db, string $slug): bool
    {
        if (self::source($slug) !== 'imported') {
            return false;
        }
        $db->query('DELETE FROM design_characters WHERE slug = ?', [$slug]);
        self::reset();

        return true;
    }

}
