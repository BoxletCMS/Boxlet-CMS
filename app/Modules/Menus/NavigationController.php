<?php

namespace App\Modules\Menus;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Pages\PageLinks;
use App\Modules\Settings\ChromeWords;
use App\Modules\Settings\SiteChrome;
use App\Support\Url;

/**
 * NAVIGATION (PLAN.md D-180, README 5.9): what the header and footer SAY and where they lead —
 * which menu the header shows, its button's words and address, each footer column's title,
 * words and menu, the small print — with the menus themselves under them.
 *
 * Moved here from Appearance, which keeps how they look. They were one form with the design
 * (D-059), so publishing a design wrote every word and menu choice again from whatever the
 * form posted; now neither screen posts the other's fields, and neither can take the other's
 * away (appearance_navigation_test).
 *
 * The form's field names are the ones Appearance used (`header_menu`, `footer_menu_N`,
 * ChromeWords::field()), so the words' checking and storing are SiteChrome's and ChromeWords'
 * as before.
 */
final class NavigationController
{
    /** Which menu the header shows, posted by name. A field name is not a settings key. */
    public const MENU = 'header_menu';

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        return $this->render($this->stored(), [], []);
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $words = ChromeWords::fromRequest($request, $this->locales());
        $state = [
            'menu' => trim($request->input(self::MENU)),
            'footer_menus' => self::footerMenusFrom($request),
            'words' => $words['values'],
        ];
        if ($words['errors'] !== []) {
            return $this->render($state, $words['errors'], [], 422, t('navigation.not_saved'));
        }

        // A menu is chosen by name, and a name no menu carries any more is cleared rather
        // than stored: the header would render nothing for it, and a setting that silently
        // means nothing is worse than an empty one the owner can see.
        $menus = Menu::names($db);
        $goneMenu = $state['menu'] !== '' && !in_array($state['menu'], $menus, true);
        // Each footer column's menu likewise (D-115); `header` and `none` are choices, not
        // names, and a name no menu carries is stored as none.
        $footerMenus = [];
        $goneFooterMenu = false;
        foreach ($state['footer_menus'] as $n => $name) {
            $gone = $name !== '' && $name !== SiteChrome::FOOTER_MENU_HEADER && $name !== SiteChrome::FOOTER_MENU_NONE && !in_array($name, $menus, true);
            $goneFooterMenu = $goneFooterMenu || $gone;
            $footerMenus[$n] = $gone ? SiteChrome::FOOTER_MENU_NONE : $name;
        }
        SiteChrome::saveShared($db, $goneMenu ? '' : $state['menu'], $footerMenus);
        ChromeWords::save($db, $state['words']);
        Activity::record($db, 'design', 'header_saved', null, '');

        $message = t('navigation.saved');
        if ($goneMenu || $goneFooterMenu) {
            $message .= ' ' . t('chrome.menu_gone');
        }
        $session = $this->container->get('session');
        $session->set('flash', $message);
        $session->set('flash_kind', $goneMenu || $goneFooterMenu ? 'warning' : 'success');

        return Response::redirect(Url::admin('navigation'));
    }

    /**
     * The screen for a state of the header's and footer's words, with the menus under it.
     * MenusController draws it too, when a new menu is refused.
     *
     * @param array{menu: string, footer_menus: array<int, string>, words: array<string, array<string, mixed>>} $state
     * @param array<string, string> $errors the words', keyed by the field at fault
     * @param array<string, string> $menuErrors the new menu's
     */
    public function render(array $state, array $errors, array $menuErrors, int $status = 200, ?string $notice = null): Response
    {
        $db = $this->db();
        $locales = $this->locales();

        return AdminView::render($this->container, __DIR__ . '/views', 'navigation', [
            'title' => t('navigation.title'),
            'nav' => 'menus',
            'wide' => true,
            // The footer's words are rich text (D-113): TipTap and the field script that binds it.
            'styles' => ['admin-pages.css', 'admin-richtext.css', 'admin-richtext-tags.css', 'admin-navigation.css'],
            'scripts' => ['vendor/tiptap.bundle.min.js', 'richtext-link.js', 'richtext.js', 'navigation.js'],
            'menu' => $state['menu'],
            'footerMenus' => $state['footer_menus'],
            'menuNames' => Menu::names($db),
            'words' => $state['words'],
            'errors' => $errors,
            'notice' => $notice,
            'menus' => Menu::all($db),
            'menuErrors' => $menuErrors,
            'locales' => $this->container->get('locales'),
            'shownLocale' => Url::primaryLocale() !== '' ? Url::primaryLocale() : ($locales[0] ?? 'en'),
            // What the button may point at, per language: a Croatian header links to
            // Croatian pages (D-034).
            'linkPages' => array_combine($locales, array_map(
                static fn (string $code): array => PageLinks::choices($db, $code),
                $locales,
            )),
        ], $status);
    }

    /**
     * What the site has now.
     *
     * @return array{menu: string, footer_menus: array<int, string>, words: array<string, array<string, mixed>>}
     */
    public function stored(): array
    {
        $db = $this->db();

        return [
            'menu' => SiteChrome::menuName($db),
            'footer_menus' => SiteChrome::footerMenus($db),
            'words' => ChromeWords::stored($db, $this->locales()),
        ];
    }

    /**
     * Each footer column's menu as posted (D-115): '' none, `header`, or a name.
     *
     * @return array<int, string>
     */
    private static function footerMenusFrom(Request $request): array
    {
        $menus = [];
        foreach (range(1, SiteChrome::FOOTER_COLUMNS) as $n) {
            $menus[$n] = trim($request->input('footer_menu_' . $n));
        }

        return $menus;
    }

    /** @return list<string> */
    private function locales(): array
    {
        return array_values(array_map(
            static fn (array $locale): string => (string) $locale['code'],
            $this->container->get('locales'),
        ));
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
