<?php

namespace App\Modules\Demo;

use App\Core\Db;
use App\Modules\Menus\Menu;
use App\Modules\Settings\SiteChrome;

/**
 * The demo's menus and footer (PLAN.md D-213): the header's menu over the six pages a visitor
 * looks for first, a footer menu for the rest (Boxlet has no automatic "More"), and the
 * footer's columns — the address with that menu under it, and the hours — in each of its
 * languages. The menu is in the first column because every footer draws the first: a set
 * whose footer has one or two columns hid a third, and Hire, About and Journal with it (the
 * demo's shots in all 21 sets, D-213).
 *
 * WITHOUT A MENU THE DEMO HAS NO HEADER AT ALL. An empty header is deliberately not drawn
 * (PLAN.md D-032); the demo exists to be a site worth looking at, and a site without
 * navigation is not one.
 */
final class DemoChrome
{
    /**
     * The menus in the site's own language, and the chrome pointed at them: the header's
     * Main, the first footer column's Footer, nothing in the others.
     *
     * @param list<array{key: string, menu: string}> $pages
     * @param array<string, int> $ids page key => id
     */
    public static function menus(Db $db, string $locale, string $lang, array $pages, array $ids): void
    {
        $main = Menu::create($db, $locale, 'Main');
        $footer = Menu::create($db, $locale, 'Footer');
        foreach ($pages as $page) {
            if ($page['menu'] === 'main' || $page['menu'] === 'footer') {
                Menu::addItem($db, $page['menu'] === 'main' ? $main : $footer, null, $ids[$page['key']], null, null);
            }
        }
        SiteChrome::saveShared($db, 'Main', [1 => 'Footer', 2 => SiteChrome::FOOTER_MENU_NONE, 3 => SiteChrome::FOOTER_MENU_NONE]);
        self::footer($db, $locale, $lang);
    }

    /**
     * The menus of a language the demo translated only some pages into: those pages, in the
     * order the site's own menus have them.
     *
     * @param array<string, int> $translated page key => id, in $locale
     * @param list<array{key: string, menu: string}> $pages
     */
    public static function translatedMenus(Db $db, string $locale, array $pages, array $translated): void
    {
        $main = Menu::create($db, $locale, 'Main');
        $footer = Menu::create($db, $locale, 'Footer');
        foreach ($pages as $page) {
            if (isset($translated[$page['key']]) && ($page['menu'] === 'main' || $page['menu'] === 'footer')) {
                Menu::addItem($db, $page['menu'] === 'main' ? $main : $footer, null, $translated[$page['key']], null, null);
            }
        }
        self::footer($db, $locale, $locale === 'hr' ? 'hr' : 'en');
    }

    /** The footer's words in one language: the address, the hours, and a line saying what this is. */
    private static function footer(Db $db, string $locale, string $lang): void
    {
        $t = static fn (string $hr, string $en): string => $lang === 'hr' ? $hr : $en;
        SiteChrome::saveForLocale($db, $locale, [
            'columns' => [
                ['title' => 'The Printworks', 'text' => '<p>' . $t('Foundry Lane 14', '14 Foundry Lane') . '<br><a href="mailto:hello@theprintworks.example">hello@theprintworks.example</a></p>'],
                ['title' => $t('Radno vrijeme', 'Opening hours'), 'text' => '<p>' . $t('Utorak–subota: 10–20', 'Tuesday–Saturday: 10 am–8 pm') . '<br>' . $t('Nedjelja: 10–15', 'Sunday: 10 am–3 pm') . '<br>' . $t('Ponedjeljak: zatvoreno', 'Monday: closed') . '</p>'],
                ['title' => '', 'text' => ''],
            ],
            'small_print' => $t('The Printworks je izmišljeno mjesto: demo stranica izrađena u Boxletu.', 'The Printworks is a made-up place: a demo site built with Boxlet.'),
        ]);
    }
}
