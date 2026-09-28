<?php

namespace App\Modules\Languages;

use App\Core\Db;

/**
 * The site's languages (SPEC §5.2 `locales`, PLAN.md D-043): which exist, which are
 * switched on, and in what order a visitor is offered them.
 *
 * THE PRIMARY LANGUAGE IS FIXED. It is chosen at install, carries the unprefixed addresses
 * (SPEC §5.1) and is what every other language falls back to, so it can be neither switched
 * off nor removed here. Changing it would move every address on the site.
 *
 * A language is REMOVED only while nothing is written in it — no page and no menu. With
 * content it can be switched off instead, which takes its addresses off the site and keeps
 * the work: removing it would have to delete pages, or leave rows naming a language that no
 * longer exists, and the foreign keys on pages and menus refuse the second.
 */
final class Locales
{
    /**
     * The list the installer offers, keyed by ISO 639-1 code, each name in its own language.
     *
     * @return array<string, string>
     */
    public static function known(): array
    {
        /** @var array<string, string> */
        return require dirname(__DIR__) . '/I18n/languages.php';
    }

    /**
     * Every language, primary first then in the order visitors see them, with how many
     * pages each holds.
     *
     * @return list<array{code: string, label: string, primary: bool, enabled: bool, pages: int}>
     */
    public static function all(Db $db): array
    {
        $rows = $db->all(
            'SELECT l.code, l.label, l.is_primary, l.enabled,
                    (SELECT COUNT(*) FROM pages p WHERE p.locale = l.code) AS pages
             FROM locales l ORDER BY l.is_primary DESC, l.sort, l.code'
        );

        return array_values(array_map(static fn (array $row): array => [
            'code' => (string) $row['code'],
            'label' => (string) $row['label'],
            'primary' => (int) $row['is_primary'] === 1,
            'enabled' => (int) $row['enabled'] === 1,
            'pages' => (int) $row['pages'],
        ], $rows));
    }

    /**
     * The languages that can still be added, by code, each with its own name.
     *
     * @return array<string, string>
     */
    public static function addable(Db $db): array
    {
        $taken = array_column($db->all('SELECT code FROM locales'), 'code');

        return array_diff_key(self::known(), array_flip(array_map('strval', $taken)));
    }

    /**
     * Adds a language, switched on and last in the order, falling back to the primary
     * (D-043). Returns an error message, or null when it was added.
     */
    public static function add(Db $db, string $code): ?string
    {
        $known = self::known();
        if (!isset($known[$code])) {
            return t('languages.unknown');
        }
        if ($db->one('SELECT code FROM locales WHERE code = ?', [$code]) !== null) {
            return t('languages.exists', ['language' => $known[$code]]);
        }
        $primary = self::primary($db);
        $next = (int) ($db->one('SELECT MAX(sort) AS n FROM locales')['n'] ?? 0) + 1;
        $db->query(
            'INSERT INTO locales (code, label, is_primary, fallback, sort, enabled) VALUES (?, ?, 0, ?, ?, 1)',
            [$code, $known[$code], $primary, $next],
        );

        return null;
    }

    /** Switches a language on or off. The primary cannot be switched off. */
    public static function setEnabled(Db $db, string $code, bool $enabled): ?string
    {
        $row = $db->one('SELECT is_primary FROM locales WHERE code = ?', [$code]);
        if ($row === null) {
            return t('languages.unknown');
        }
        if ((int) $row['is_primary'] === 1 && !$enabled) {
            return t('languages.primary_fixed');
        }
        $db->query('UPDATE locales SET enabled = ? WHERE code = ?', [$enabled ? 1 : 0, $code]);

        return null;
    }

    /**
     * Moves a language one place up or down among the others. The primary is always
     * first and is not moved, and nothing moves past it.
     */
    public static function move(Db $db, string $code, string $direction): void
    {
        // Renumbered first: every row installed before this screen existed has sort 0,
        // and swapping two zeros moves nothing.
        $order = array_map('strval', array_column(
            $db->all('SELECT code FROM locales WHERE is_primary = 0 ORDER BY sort, code'),
            'code',
        ));
        $at = array_search($code, $order, true);
        if ($at === false) {
            return;
        }
        $to = $direction === 'up' ? $at - 1 : $at + 1;
        if ($to < 0 || $to >= count($order)) {
            return;
        }
        [$order[$at], $order[$to]] = [$order[$to], $order[$at]];

        $db->transaction(static function () use ($db, $order): void {
            foreach ($order as $sort => $each) {
                $db->query('UPDATE locales SET sort = ? WHERE code = ?', [$sort + 1, $each]);
            }
        });
    }

    /**
     * Removes a language nothing is written in. Returns an error message, or null.
     * Its alt texts go with it: they are the one thing stored per language that is not
     * content someone would look for in the language's own pages.
     */
    public static function remove(Db $db, string $code): ?string
    {
        $row = $db->one('SELECT label, is_primary FROM locales WHERE code = ?', [$code]);
        if ($row === null) {
            return t('languages.unknown');
        }
        if ((int) $row['is_primary'] === 1) {
            return t('languages.primary_fixed');
        }
        $pages = (int) ($db->one('SELECT COUNT(*) AS n FROM pages WHERE locale = ?', [$code])['n'] ?? 0);
        $menus = (int) ($db->one('SELECT COUNT(*) AS n FROM menus WHERE locale = ?', [$code])['n'] ?? 0);
        if ($pages > 0 || $menus > 0) {
            return t('languages.in_use', ['language' => (string) $row['label'], 'pages' => $pages, 'menus' => $menus]);
        }
        $db->transaction(static function () use ($db, $code): void {
            $db->query('DELETE FROM media_meta WHERE locale = ?', [$code]);
            $db->query('UPDATE locales SET fallback = NULL WHERE fallback = ?', [$code]);
            $db->query('DELETE FROM locales WHERE code = ?', [$code]);
        });

        return null;
    }

    public static function primary(Db $db): string
    {
        return (string) ($db->one('SELECT code FROM locales WHERE is_primary = 1')['code'] ?? '');
    }
}
