<?php

namespace App\Modules\Admin;

use App\Core\Db;
use App\Modules\Stats\Tracker;
use App\Support\Url;

/**
 * One ranked list across the admin (PLAN.md D-052): its screens, what can be done, the
 * site's pages, pictures, menus and forms, and each setting by name — "time zone" lands on
 * the field, not on the Settings screen's top.
 *
 * Ranked by kind, then by where the words were found: a name that starts with them before
 * one that only contains them.
 */
final class Search
{
    private const LIMIT = 20;

    /** The order kinds are listed in: where you go first, then what you do, then things. */
    private const KINDS = ['go', 'setting', 'action', 'page', 'picture', 'menu', 'form'];

    /**
     * @return list<array{kind: string, label: string, hint: string, href: string}>
     */
    public static function find(Db $db, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        // '!' as the LIKE escape: a backslash is an escape inside MySQL's string literals and
        // not inside SQLite's, so ESCAPE '\' cannot be written once for both (SPEC §5.0).
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';

        $found = [];
        foreach (self::fixed($db) as $entry) {
            if (self::matches($query, $entry['label'] . ' ' . $entry['words'])) {
                $found[] = ['kind' => $entry['kind'], 'label' => $entry['label'], 'hint' => $entry['hint'], 'href' => $entry['href']];
            }
        }

        foreach ($db->all("SELECT id, title, slug, locale FROM pages WHERE title LIKE ? ESCAPE '!' OR slug LIKE ? ESCAPE '!' ORDER BY title LIMIT 10", [$like, $like]) as $row) {
            $found[] = ['kind' => 'page', 'label' => (string) $row['title'], 'hint' => Url::page((string) $row['locale'], (string) $row['slug']), 'href' => Url::admin('pages', (int) $row['id'])];
        }
        foreach ($db->all("SELECT id, filename FROM media WHERE filename LIKE ? ESCAPE '!' OR original_name LIKE ? ESCAPE '!' ORDER BY filename LIMIT 10", [$like, $like]) as $row) {
            $found[] = ['kind' => 'picture', 'label' => (string) $row['filename'], 'hint' => t('admin.nav.media'), 'href' => Url::admin('media', (int) $row['id'])];
        }
        foreach ($db->all("SELECT id, name FROM menus WHERE name LIKE ? ESCAPE '!' ORDER BY name LIMIT 5", [$like]) as $row) {
            $found[] = ['kind' => 'menu', 'label' => (string) $row['name'], 'hint' => t('admin.nav.menus'), 'href' => Url::admin('menus', (int) $row['id'])];
        }
        foreach ($db->all("SELECT id, name FROM forms WHERE name LIKE ? ESCAPE '!' ORDER BY name LIMIT 5", [$like]) as $row) {
            $found[] = ['kind' => 'form', 'label' => (string) $row['name'], 'hint' => t('admin.nav.forms'), 'href' => Url::admin('forms', (int) $row['id'])];
        }

        $needle = mb_strtolower($query);
        usort($found, static fn (array $a, array $b): int => [
            array_search($a['kind'], self::KINDS, true),
            str_starts_with(mb_strtolower($a['label']), $needle) ? 0 : 1,
            $a['label'],
        ] <=> [
            array_search($b['kind'], self::KINDS, true),
            str_starts_with(mb_strtolower($b['label']), $needle) ? 0 : 1,
            $b['label'],
        ]);

        return array_slice($found, 0, self::LIMIT);
    }

    /**
     * Every word of the query somewhere in the text, in any order and case.
     */
    private static function matches(string $query, string $text): bool
    {
        $text = mb_strtolower($text);
        foreach (preg_split('~\s+~', mb_strtolower($query)) ?: [] as $word) {
            if ($word !== '' && !str_contains($text, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The screens, the actions and the settings: what the admin has rather than what the
     * site holds. Words are extra terms each is also found by.
     *
     * @return list<array{kind: string, label: string, hint: string, href: string, words: string}>
     */
    private static function fixed(Db $db): array
    {
        $screens = [
            [t('admin.nav.dashboard'), Url::admin(), 'dashboard home start'],
            [t('admin.nav.pages'), Url::admin('pages'), ''],
            [t('admin.nav.media'), Url::admin('media'), 'pictures images photos library'],
            [t('admin.nav.menus'), Url::admin('menus'), 'navigation'],
            [t('admin.nav.forms'), Url::admin('forms'), 'contact messages'],
            [t('admin.nav.appearance'), Url::admin('appearance'), 'design character colours colors fonts type header footer chrome'],
            [t('admin.nav.settings'), Url::admin('settings'), ''],
            [t('activity.title'), Url::admin('activity'), 'log history changes'],
        ];
        if (Tracker::settings($db)['enabled']) {
            $screens[] = [t('admin.nav.statistics'), Url::admin('statistics'), 'visitors views analytics'];
        }

        $entries = [];
        foreach ($screens as [$label, $href, $words]) {
            $entries[] = ['kind' => 'go', 'label' => $label, 'hint' => '', 'href' => $href, 'words' => $words];
        }

        foreach ([
            [t('pages.new'), Url::admin('pages', 'new'), 'create add page'],
            [t('media.upload'), Url::admin('media'), 'add pictures images documents pdf download'],
            [t('menus.new'), Url::admin('menus'), 'create add menu'],
            [t('forms.new'), Url::admin('forms'), 'create add form'],
            [t('admin.view_site'), Url::asset(''), 'open live site'],
        ] as [$label, $href, $words]) {
            $entries[] = ['kind' => 'action', 'label' => $label, 'hint' => '', 'href' => $href, 'words' => $words];
        }

        $settings = Url::admin('settings');
        foreach ([
            [t('settings.site_name'), $settings . '#site_name', 'title name'],
            [t('settings.timezone'), $settings . '#timezone', 'time zone clock'],
            [t('settings.logo'), $settings . '#site_logo', 'branding'],
            [t('settings.favicon'), $settings . '#site_favicon', 'branding icon tab'],
            [t('settings.share_image'), $settings . '#site_share_image', 'branding social sharing'],
            [t('languages.title'), $settings . '#languages', 'language translation'],
            [t('mail.title'), $settings . '#mail', 'mail smtp resend sending'],
            [t('twofactor.title'), $settings . '#two-step', 'security login 2fa code'],
            [t('stats.title'), $settings . '#statistics', 'statistics tracking privacy'],
            [t('maintenance.title'), $settings . '#maintenance', 'closed offline'],
        ] as [$label, $href, $words]) {
            $entries[] = ['kind' => 'setting', 'label' => $label, 'hint' => t('admin.nav.settings'), 'href' => $href, 'words' => $words];
        }

        return $entries;
    }
}
