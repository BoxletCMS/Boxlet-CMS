<?php

namespace App\Modules\Design;

use App\Core\Db;
use App\Core\Settings;
use App\Modules\Design\Vocabulary\Decisions;
use Throwable;

/**
 * The site's saved design (PLAN.md D-164): the OWNER'S values of the global decisions — the
 * header and footer included — one row in design_tokens per value set, nothing for a key that
 * follows; compiled, over the character the site composes with, into the stylesheet whose
 * file name is recorded in settings.tokens_css.
 *
 * Changing the character therefore keeps every value the owner set (README 1.1): the rows do
 * not move, only what fills the keys they leave empty.
 */
final class Design
{
    /** Where font files live as seen from the compiled stylesheet in public/cache. */
    private const FONTS_FROM_CACHE = '../assets/fonts';

    /**
     * The owner's values: every key of the vocabulary, '' for one that follows. A stored value
     * the vocabulary no longer takes is '' rather than an error, so a damaged row can never
     * break rendering.
     *
     * @return array<string, string>
     */
    public static function load(Db $db): array
    {
        $stored = [];
        foreach ($db->all('SELECT group_key, value_json FROM design_tokens') as $row) {
            $stored[(string) $row['group_key']] = json_decode((string) $row['value_json'], true);
        }
        $stored = Tokens::upgraded($stored);
        $values = [];
        foreach (array_keys(Decisions::ALL) as $key) {
            $value = $stored[$key] ?? '';
            $values[$key] = Decisions::clean($key, is_int($value) || is_float($value) ? (string) $value : $value) ?? '';
        }

        return $values;
    }

    /**
     * The owner's colours for dark mode only (D-187): each of Decisions::DARK_OWN, '' where the
     * owner has none and the colour they hold for both modes, or the character's, stands.
     * Rows `dark.<key>` of design_tokens.
     *
     * @return array<string, string>
     */
    public static function dark(Db $db): array
    {
        $stored = [];
        foreach ($db->all("SELECT group_key, value_json FROM design_tokens WHERE group_key LIKE 'dark.%'") as $row) {
            $stored[substr((string) $row['group_key'], 5)] = json_decode((string) $row['value_json'], true);
        }

        return Tokens::darkOwn($stored);
    }

    /**
     * The design as drawn: the owner's values over the character's.
     *
     * @return array<string, string>
     */
    public static function resolved(Db $db, ?string $character = null): array
    {
        return Tokens::resolve(self::load($db), $character ?? Composition::active($db), self::dark($db));
    }

    /**
     * Stores the owner's values — every key that is not '' — and publishes the stylesheet.
     *
     * @param array<string, string> $values key => value, '' or absent for "follow"
     * @param array<string, string>|null $dark the owner's dark colours; null keeps those stored
     * @return string the new stylesheet's file name
     */
    public static function save(Db $db, array $values, string $cacheDirectory, ?array $dark = null): string
    {
        self::store($db, $values, $dark);

        return self::publish($db, $cacheDirectory);
    }

    /**
     * Writes the owner's values without compiling: every row replaced by the keys given. The
     * owner's dark colours with them when given; a caller that knows nothing of them — a
     * header's look saved alone — keeps them as they are.
     *
     * @param array<string, string> $values
     * @param array<string, string>|null $dark
     */
    public static function store(Db $db, array $values, ?array $dark = null): void
    {
        $dark = Tokens::darkOwn($dark ?? self::dark($db));
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        try {
            $db->query('DELETE FROM design_tokens');
            foreach (array_keys(Decisions::ALL) as $key) {
                $value = (string) ($values[$key] ?? '');
                if ($value !== '') {
                    $db->query('INSERT INTO design_tokens (group_key, value_json) VALUES (?, ?)', [$key, json_encode($value, JSON_THROW_ON_ERROR)]);
                }
            }
            foreach ($dark as $key => $value) {
                if ($value !== '') {
                    $db->query('INSERT INTO design_tokens (group_key, value_json) VALUES (?, ?)', ['dark.' . $key, json_encode($value, JSON_THROW_ON_ERROR)]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Compiles the design as drawn to cache/tokens.{hash}.css and records the file name.
     */
    public static function publish(Db $db, string $cacheDirectory): string
    {
        $resolved = self::resolved($db);
        $file = (new TokenCompiler())->compile(
            Derived::from($resolved, Composition::section(Composition::active($db), [])['width']),
            $cacheDirectory,
            Typography::fontFaces([$resolved['heading_font'], $resolved['body_font']], self::FONTS_FROM_CACHE),
            self::code(),
        );
        Settings::set($db, 'tokens_css', $file);
        Settings::set($db, 'tokens_code', self::code());

        return $file;
    }

    /**
     * The stylesheet file every page links. Normally a settings read; it compiles when the
     * recorded file is missing, so a site never renders unstyled, and when it was compiled by
     * other code (D-189, O-53): code put on the server by FTP or git, not by the admin's
     * update, changed what the tokens derive and the site kept the old file until its design
     * was next published.
     */
    public static function stylesheet(Db $db, string $cacheDirectory): string
    {
        $file = Settings::get($db, 'tokens_css');
        if (is_string($file) && preg_match('~^tokens\.[0-9a-f]{12}\.css$~', $file) && is_file($cacheDirectory . '/' . $file)
            && Settings::get($db, 'tokens_code') === self::code()) {
            return $file;
        }

        return self::publish($db, $cacheDirectory);
    }

    /**
     * The version of the code that turns a design into tokens.css: a hash of this module's
     * sources and the core characters', read once a request. xxh128, not a release number,
     * so a fix put on the server any way at all counts.
     */
    public static function code(): string
    {
        static $code = null;
        if ($code === null) {
            $root = dirname(__DIR__, 3);
            $files = array_merge(
                glob(__DIR__ . '/*.php') ?: [],
                glob(__DIR__ . '/Vocabulary/*.php') ?: [],
                glob($root . '/designs/core/*.json') ?: [],
            );
            sort($files);
            $code = hash('xxh128', implode('', array_map(static fn (string $f): string => (string) hash_file('xxh128', $f), $files)));
        }

        return $code;
    }
}
