<?php

namespace App\Modules\Pages;

use App\Core\Container;
use App\Core\Db;
use App\Core\Settings;
use App\Modules\Design\Design;
use App\Modules\Design\Palette;
use App\Modules\Design\Color;
use App\Modules\Design\Tokens;
use App\Modules\Media\MediaPicture;
use App\Modules\Menus\MenuTree;
use App\Modules\Settings\ChromeLook;
use App\Modules\Settings\ChromeWords;
use App\Modules\Settings\SiteChrome;

/**
 * ONE SOURCE FOR EVERY VARIABLE Pages/views/layout.php READS (PLAN.md D-057).
 *
 * There are two renderers of the site's layout — a visitor's page, and the admin's design
 * preview — and until now the second answered "what does that layout need?" from a literal
 * array somebody had to remember to update. It was caught out three times: by `description`,
 * then by `icon`, then by the chrome of slice 5c. The comment above it said, correctly, that
 * the real fix is one source for these variables rather than a louder warning. This is it.
 *
 * Two locks, so there is no fourth time:
 *   - the declared return shape below. A factory that DROPS a key fails PHPStan before any
 *     test runs.
 *   - tests/page_layout_test.php reads the template itself and compares what it uses with
 *     KEYS. A variable the template GROWS fails there, by name. That is the half the test
 *     suite could not do on its own: it fails on any notice, but only along the branch some
 *     test happens to render, and `icon` sits behind an `if`.
 *
 * @phpstan-type LayoutData array{
 *     title: string,
 *     description: string,
 *     canonical: string|null,
 *     icon: array{url: string, type: string}|null,
 *     shareImage: string|null,
 *     hreflang: list<array{hreflang: string, href: string}>,
 *     breadcrumbs: string,
 *     headerBleed: string,
 *     footerBleed: string,
 *     headerHtml: string,
 *     footerHtml: string,
 * }
 */
final class PageLayoutData
{
    /**
     * What the layout is handed, beside View's own $locale and $content. The test above
     * asserts this list against the template, so it is a fact rather than a comment.
     */
    public const KEYS = ['title', 'description', 'canonical', 'icon', 'shareImage', 'hreflang', 'breadcrumbs', 'headerBleed', 'footerBleed', 'headerHtml', 'footerHtml'];

    /**
     * A visitor's page, or an error page.
     *
     * $head carries what only the caller knows — the title, and for a real page its
     * description, canonical address and sharing picture. The rest is the same for every
     * page and is assembled here: an error page carries the icon and the chrome too, because
     * a "not found" without the site's own header around it reads as a broken site rather
     * than a wrong address.
     *
     * @param array{title: string, description?: string, canonical?: string|null, shareImage?: string|null, first_surface?: string, breadcrumbs?: string} $head
     * @param array<string, mixed>|null $page the page being drawn; null on an error page
     * @param string $current its address, for marking the menu; '' on an error page
     * @return LayoutData
     */
    public static function forPage(Container $container, string $locale, array $head, ?array $page = null, string $current = ''): array
    {
        $db = $container->get('db');
        // The languages as a visitor is offered them: this page in each, or that language's
        // home where it is not translated (D-043, step 4). What the switcher draws, and what
        // hreflang is made from.
        $alternates = Alternates::for($db, $page, $container->get('locales'));

        return [
            'title' => $head['title'],
            // An empty description is better than one repeating the title, so it stays empty
            // and the tag is dropped (D-004).
            'description' => $head['description'] ?? '',
            'canonical' => $head['canonical'] ?? null,
            // One indexed lookup per render, and null when no favicon is chosen.
            'icon' => SiteChrome::icon($db),
            // A link preview of an error page is not worth a row, so this is the caller's.
            'shareImage' => $head['shareImage'] ?? null,
            'hreflang' => Alternates::hreflang($alternates, self::primary($container->get('locales'))),
            // BreadcrumbList JSON-LD for a page under a parent, '' otherwise (D-129).
            'breadcrumbs' => $head['breadcrumbs'] ?? '',
        ] + self::chrome($container, $locale, $alternates, $current, self::design($db) + ['first_surface' => $head['first_surface'] ?? '']);
    }

    /**
     * The admin's preview of a design.
     *
     * It draws the real chrome, which the preview deliberately did NOT do before: the old
     * screen existed to judge the tokens and section styles of one character, and chrome
     * around the specimen was furniture competing with the thing being looked at. On the
     * screen this is being built for, the header and footer are among the things being
     * judged, so the reason went with the old screen (PLAN.md D-057).
     *
     * $trying is WHAT THE OWNER IS DOING AND HAS NOT SAVED, and every part of it is
     * optional: the character being previewed, the look choices, which menu, and the words.
     * One array rather than four parameters, because the screen that fills it grows: they
     * all mean the same thing — draw the site as it WOULD be, not as it is.
     *
     * A choice left at "follow the character" follows the character being PREVIEWED, so
     * Bold's sections never stand under Minimal's header.
     *
     * @param array{look?: array<string, string>, character?: string, menu?: string|null, footer_menus?: array<int, string>, words?: array<string, string>, bleeds?: array<string, string>, own?: array<string, string>, decisions?: array<string, string>, first_surface?: string} $trying
     * @return LayoutData
     */
    public static function forPreview(Container $container, string $locale, string $title, array $trying = []): array
    {
        $db = $container->get('db');

        return [
            'title' => $title,
            // A preview describes no page in particular, so it gives no description and the
            // layout emits no tag (D-004).
            'description' => '',
            'canonical' => null,
            'icon' => SiteChrome::icon($db),
            'shareImage' => null,
            // An admin address must never announce itself as a translation of anything.
            'hreflang' => [],
            'breadcrumbs' => '',
        ] + self::chrome($container, $locale, Alternates::for($db, null, $container->get('locales')), '', $trying);
    }

    /**
     * What a visitor's page hands the chrome from the DESIGN rather than from the chrome's
     * own settings: which side of the frame each part renders on (D-067), and whether it
     * carries a colour of the owner's (D-076). The preview builds the same two from the
     * query it is drawing (AppearancePreview), so both renderers agree on what a decision
     * means to the markup.
     *
     * @return array{bleeds: array<string, string>, own: array<string, string>, decisions: array<string, string>}
     */
    private static function design(Db $db): array
    {
        $decisions = Design::load($db);

        return ['bleeds' => $decisions, 'own' => Tokens::ownChrome($decisions), 'decisions' => $decisions];
    }

    /**
     * WHETHER THE INK ON THE HEADER IS LIGHT, which is what chooses the logo for dark surfaces
     * (D-112). Not "is the surface called contrast": a contrast surface can be pale (Soft's
     * is cream) and a page set dark by hand makes a plain header dark. So it is measured on
     * the ink the palette actually puts there — the same ink the header's words are drawn in.
     *
     * A header laid over the first section takes that section's ink; a colour of the owner's
     * own takes the ink derived for it (D-076); otherwise the surface's.
     *
     * @param array<string, string> $look the resolved look
     * @param array<string, string> $own colour of the owner's own per part
     * @param array<string, string> $decisions the design, for the palette
     */
    private static function inkIsLight(array $look, array $own, array $decisions, string $firstSurface): bool
    {
        if ($decisions === []) {
            return false;
        }
        $colors = Palette::colors($decisions['seed'], $decisions['secondary'], $decisions['surface_contrast'], Tokens::byHand($decisions));
        $surface = ($look['header_behaviour'] ?? '') === 'over' ? $firstSurface : ($look['header_surface'] ?? 'plain');
        if (($look['header_behaviour'] ?? '') !== 'over' && isset($own['header'])) {
            $ink = Palette::inksOn($own['header'], $colors)['text'];
        } else {
            $ink = $colors[match ($surface) {
                'contrast', 'image' => 'on-contrast',
                'gradient' => 'on-gradient',
                default => 'text',
            }];
        }

        return Color::toOklch($ink)[0] > 0.5;
    }

    /**
     * The site's header and footer, drawn by the block machinery so they inherit the design
     * tokens and the section style layers (PLAN.md D-028, D-030).
     *
     * THE MENU IS RESOLVED ONCE, here, and handed to both templates — the same rule pictures
     * follow. A template asks the database nothing. The chrome stores the menu's NAME, so a
     * menu deleted and made again under that name simply works, and nothing dangles when it
     * is not.
     *
     * NEITHER IS DRAWN EMPTY. A header with no logo, button or menu has nothing to show, and
     * an empty <header> on every page is noise. The footer's condition carries two extra
     * terms: it owns the language switcher, so a site with two locales and no footer content
     * still needs its footer, and it carries the Boxlet credit when that is switched on.
     *
     * THE CURRENT PAGE IS MARKED HERE, on the resolved menu, rather than handed to the
     * templates as an address to compare: which entry is this page is a resolution like any
     * other, and the templates stay free of URL arithmetic (PLAN.md D-032).
     *
     * @param array<int, array<string, mixed>> $locales the languages, as the switcher shows them
     * @param array{look?: array<string, string>, character?: string, menu?: string|null, footer_menus?: array<int, string>, words?: array<string, string>, bleeds?: array<string, string>, own?: array<string, string>, decisions?: array<string, string>, first_surface?: string} $trying
     *        what an admin preview is showing unsaved; for a visitor's page only the two
     *        bleeds, which are design decisions rather than chrome ones (D-067)
     * @return array{headerBleed: string, footerBleed: string, headerHtml: string, footerHtml: string}
     */
    private static function chrome(Container $container, string $locale, array $locales, string $current, array $trying): array
    {
        $db = $container->get('db');
        $registry = $container->get('chrome');

        // Which menu: the one being TRIED on the screen, else the one the site shows. A
        // request naming no menu is not a request for no menu — only a choice of '' is.
        $menuName = array_key_exists('menu', $trying) && $trying['menu'] !== null ? $trying['menu'] : SiteChrome::menuName($db);
        $resolveMenu = static function (string $name) use ($db, $locale, $current): array {
            $menu = [];
            foreach (MenuTree::forVisitors($db, $locale, $name) as $item) {
                $children = [];
                $below = false;
                foreach ($item['children'] as $child) {
                    $children[] = $child + ['current' => $current !== '' && $child['url'] === $current];
                    $below = $below || ($current !== '' && $child['url'] === $current);
                }
                $menu[] = ['children' => $children, 'current' => $current !== '' && $item['url'] === $current, 'current_parent' => $below] + $item;
            }

            return $menu;
        };
        $menu = $resolveMenu($menuName);
        // EACH FOOTER COLUMN'S MENU (D-115): none, the header's, or a menu of its own —
        // what the screen is trying for a column, else what is stored.
        $footerMenus = [];
        $stored = SiteChrome::footerMenus($db);
        foreach ($stored as $n => $name) {
            $name = $trying['footer_menus'][$n] ?? $name;
            $footerMenus[$n] = match (true) {
                $name === SiteChrome::FOOTER_MENU_NONE, $name === '' => [],
                $name === SiteChrome::FOOTER_MENU_HEADER => $menu,
                default => $resolveMenu($name),
            };
        }
        // Colour, arrangement and size: what the request is trying, else the owner's choice,
        // else the character's.
        $resolved = ChromeLook::resolve($db, $trying['look'] ?? [], $trying['character'] ?? '');
        // The header's button can point at a page like any link field (D-034); followed
        // here, before the check below asks whether it leads anywhere.
        $header = SiteChrome::header($db, $locale);
        $footer = SiteChrome::footer($db, $locale);
        // The owner's own words, as they are being typed. Only for the locale on screen:
        // the preview draws one page in one language, and the others are not on it.
        $words = $trying['words'] ?? [];
        $header['button']['label'] = $words['button_label'] ?? $header['button']['label'];
        $header['button']['url'] = $words['button_url'] ?? $header['button']['url'];
        // Each column's words as HTML whatever version stored them (D-113, D-115): a plain
        // text from before becomes one paragraph with its breaks, which is what the page
        // drew for it.
        $columns = [];
        foreach (ChromeWords::COLUMN_FIELDS as $n => [$titleField, $textField]) {
            $stored = $footer['columns'][$n - 1] ?? ['title' => '', 'text' => ''];
            $columns[] = [
                'title' => $words[$titleField] ?? $stored['title'],
                'text' => ChromeWords::asHtml($words[$textField] ?? $stored['text']),
            ];
        }
        $footer['columns'] = $columns;
        $footer['small_print'] = $words['small_print'] ?? $footer['small_print'];
        $header['button'] = PageLinks::link(
            $header['button'],
            PageLinks::targets($db, $registry, $locale, [['type' => 'header', 'content' => $header]]),
        );

        // The logo is a picture like any other and has to be RESOLVED before the template
        // sees it, exactly as a block's pictures are. Handing the header an empty lookup
        // was a silent failure of my own making: the setting was saved, the template asked
        // for a tag, MediaPicture had no entry for that id and drew nothing at all. Both
        // logos (D-112): the template picks one, and a lookup missing the one it picks
        // would be that failure again.
        $logos = array_values(array_filter([$header['logo'], $header['logo_dark']], 'is_int'));
        $media = $logos === [] ? [] : MediaPicture::resolve($db, $locale, $logos);

        // The site's name, for a header with no logo to stand under, counts as something to
        // show (D-110): a page with the site's name at the top is right, and a site without a
        // logo is most sites on their first day.
        $siteName = Settings::text($db, 'site_name');
        $hasHeader = $header['logo'] !== null || $header['button']['url'] !== '' || $menu !== [] || trim($siteName) !== '';
        // "Made with Boxlet", when the owner leaves it on (O-20). It is part of what makes a
        // footer worth drawing: a site whose footer is otherwise empty still has this line,
        // and without it here the credit would be switched on and never appear.
        $credit = Settings::get($db, 'site_credit') === true ? BOXLET_SITE : '';
        $hasColumn = false;
        foreach ($footer['columns'] as $i => $column) {
            $hasColumn = $hasColumn || $column['title'] !== '' || $column['text'] !== '' || ($footerMenus[$i + 1] ?? []) !== [];
        }
        $hasFooter = $hasColumn || $footer['small_print'] !== '' || count($locales) > 1 || $credit !== '';

        // WHICH SIDE OF THE FRAME THE CHROME IS ON (D-067). A design decision, not a chrome
        // one: it is about the shape of the page, and the layout reads it so no rule in the
        // stylesheet has to ask whether the page is boxed.
        $bleeds = $trying['bleeds'] ?? [];
        // A colour of the owner's own on the header or the footer (D-076) is a class the
        // template emits, and the class is what lets the stylesheet set the section's tokens
        // without naming them in their own fallback (D-110).
        $own = $trying['own'] ?? [];
        // Which logo the header draws (D-112): the one for dark surfaces when the ink on the
        // bar is light. Decided here, where the palette and the first section are known.
        $logoDark = self::inkIsLight($resolved, $own, $trying['decisions'] ?? [], $trying['first_surface'] ?? '');

        return [
            'headerBleed' => ($bleeds['header_bleed'] ?? 'sheet') === 'full' ? 'full' : 'sheet',
            'footerBleed' => ($bleeds['footer_bleed'] ?? 'sheet') === 'full' ? 'full' : 'sheet',
            'headerHtml' => $hasHeader
                ? $registry->render('header', $header, ['surface' => $resolved['header_surface']], $resolved['header_arrangement'], $media, true, 'header', ['menu' => $menu, 'look' => $resolved, 'own' => isset($own['header']), 'site_name' => $siteName, 'logo_dark' => $logoDark], $locale, $locales)
                : '',
            'footerHtml' => $hasFooter
                // The footer's edge is a section divider (D-113): the same layer-2 key, the
                // same classes, drawn by sections.css exactly as on a band.
                ? $registry->render('footer', $footer, ['surface' => $resolved['footer_surface'], 'divider' => $resolved['footer_edge']], $resolved['footer_layout'], [], false, 'footer', ['menus' => $footerMenus, 'look' => $resolved, 'credit' => $credit, 'own' => isset($own['footer'])], $locale, $locales)
                : '',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $locales
     */
    private static function primary(array $locales): string
    {
        foreach ($locales as $each) {
            if ((int) $each['is_primary'] === 1) {
                return (string) $each['code'];
            }
        }

        return '';
    }
}
