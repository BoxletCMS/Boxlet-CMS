<?php

namespace App\Modules\Snippets;

use App\Core\Db;
use App\Support\RichText;

/**
 * SNIPPETS (PLAN.md D-201, SPEC §5.6): a few words per language, placed in rich text as
 * {{snippet:name}} and written once. Read for a page in one query (Tags), kept here.
 *
 * A NAME NEVER CHANGES once made, and a deleted snippet's tags draw nothing: a tag is the
 * owner's words in a page, and Boxlet never rewrites a page for them.
 */
final class Snippets
{
    /** What a tag may name: lower case, digits and hyphens, as an address segment. */
    public const NAME = '[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?';

    /**
     * Every snippet, by name, with its words in each language.
     *
     * @return array<string, array<string, string>> name => locale => inline rich text
     */
    public static function all(Db $db): array
    {
        $all = [];
        foreach ($db->all('SELECT name, locale, value FROM snippets ORDER BY name, locale') as $row) {
            $all[(string) $row['name']][(string) $row['locale']] = (string) $row['value'];
        }

        return $all;
    }

    /**
     * Each snippet's words in $locale, or the primary language's where it has none there —
     * the fallback a page's words never take, because a snippet is a fact, not prose: the
     * opening hours are the opening hours. '' where neither says anything.
     *
     * @return array<string, string> name => inline rich text
     */
    public static function forLocale(Db $db, string $locale, string $primary): array
    {
        $words = [];
        foreach (self::all($db) as $name => $values) {
            $words[$name] = ($values[$locale] ?? '') !== '' ? $values[$locale] : ($values[$primary] ?? '');
        }

        return $words;
    }

    /** Why a new name cannot be used, or null when it can. */
    public static function nameProblem(Db $db, string $name): ?string
    {
        if (preg_match('~^' . self::NAME . '$~', $name) !== 1) {
            return t('snippets.name_invalid');
        }
        if ($db->one('SELECT id FROM snippets WHERE name = ?', [$name]) !== null) {
            return t('snippets.name_taken', ['name' => $name]);
        }

        return null;
    }

    /**
     * A snippet's words, each language's cleaned to inline rich text and kept INLINE: a tag
     * stands inside a sentence, so the paragraphs an editor wraps them in are taken off, and
     * two become one line with a break between. A language left empty is kept as ''.
     *
     * @param array<string, string> $values locale => words as submitted
     */
    public static function save(Db $db, string $name, array $values): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($values as $locale => $value) {
            $clean = RichText::sanitize($value, RichText::INLINE);
            $clean = trim((string) preg_replace(['~</p>\s*<p>~', '~</?p>~'], ['<br>', ''], $clean));
            $clean = (string) preg_replace('~^(<br>)+|(<br>)+$~', '', $clean);
            if ($db->one('SELECT id FROM snippets WHERE name = ? AND locale = ?', [$name, $locale]) === null) {
                $db->query('INSERT INTO snippets (name, locale, value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)', [$name, $locale, $clean, $now, $now]);
            } else {
                $db->query('UPDATE snippets SET value = ?, updated_at = ? WHERE name = ? AND locale = ?', [$clean, $now, $name, $locale]);
            }
        }
    }

    public static function delete(Db $db, string $name): void
    {
        $db->query('DELETE FROM snippets WHERE name = ?', [$name]);
    }
}
