<?php

namespace App\Modules\Appearance;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Design\Composition;
use App\Modules\Design\Design;
use App\Modules\Design\Palette;
use App\Modules\Design\Presets;
use App\Modules\Design\Tokens;
use App\Modules\Menus\Menu;
use App\Modules\Pages\PageLinks;
use App\Modules\Pages\PageTree;
use App\Modules\Settings\ChromeLook;
use App\Modules\Settings\ChromeWords;
use App\Modules\Settings\SiteChrome;
use App\Support\Url;

/**
 * ONE SCREEN FOR HOW THE SITE LOOKS (PLAN.md D-059): the character, the ten decisions, the
 * header and footer, and a picture of all of it.
 *
 * It replaces two screens that were one screen cut in half. Header & footer had seven
 * choices and NO PICTURE; Design had a picture that deliberately drew no header or footer.
 * Each was incomplete in exactly the way the other would have fixed, and the owner had to
 * hold the result in their head while moving between them.
 *
 * Saving is still one ordinary form post that works without JavaScript, and applying a
 * character to a site that already has blocks still offers two explicit buttons, because
 * the second overwrites section styles chosen by hand (SPEC §5.4).
 */
final class AppearanceController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();

        return $this->screen([
            'decisions' => Design::load($db),
            'look' => ChromeLook::stored($db),
            'menu' => SiteChrome::menuName($db),
            'footer_menus' => SiteChrome::footerMenus($db),
            'words' => ChromeWords::stored($db, $this->locales()),
        ], [], null);
    }

    /**
     * Publish, or load a character into the form. A loaded character changes nothing on the
     * site until it is published: Publish is the confirmation.
     *
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $action = $request->input('action');
        $state = AppearanceForm::read($request, $this->locales());

        // Loading a character replaces the DESIGN and keeps everything else the owner has
        // typed: their words are not a preference of the character's.
        if (str_starts_with($action, 'preset:') && Presets::exists(substr($action, 7))) {
            $name = substr($action, 7);

            return $this->screen(
                ['decisions' => Presets::get($name)] + $state,
                [],
                t('design.preset_loaded', ['preset' => t('design.preset.' . $name)]),
                200,
                $name,
            );
        }
        if (str_starts_with($action, 'library:')) {
            return $this->library($request, $action, $state);
        }

        $character = $request->input('character');
        $character = Presets::exists($character) ? $character : '';

        /*
         * GIVING A COLOUR BACK TO THE PALETTE (D-074).
         *
         * AN ACTION, NOT A BOX TO UNTICK. Each hand-set colour needs a switch beside it in
         * the form, because a colour input always carries SOME colour and "is this mine"
         * cannot be read off its value (D-065) — but that is a mechanism, not something to
         * put in front of a person. The row shows one button that says what it does, and it
         * works with no script at all.
         *
         * RE-VALIDATED, NOT TRUSTED. The palette's own colour can fail a pair the owner's
         * colour passed, and a screen that stopped saying so would give up exactly the
         * guarantee D-063 moved from derivation to checking.
         */
        if (str_starts_with($action, 'colour:free')) {
            /* The seven palette roles and the three places that may take a colour of their
               own (D-076) are one list here: the button beside each says the same thing —
               give this back — and which store the decision lives in is not the owner's
               question. "Free all" frees all ten. */
            $named = $action === 'colour:free' ? null : substr($action, strlen('colour:free:'));
            $freeing = [];
            foreach (Palette::BY_HAND as $role) {
                if ($named === null || $named === $role) {
                    $freeing[] = 'color_' . $role;
                }
            }
            foreach (Tokens::OWN_COLOURS as $field) {
                if ($named === null || $named === $field) {
                    $freeing[] = $field;
                }
            }
            foreach ($freeing as $field) {
                $state['decisions'][$field] = '';
            }
            $again = Tokens::validate($state['decisions']);

            return $this->screen(
                ['decisions' => $again['decisions']] + $state,
                $again['errors'],
                $freeing === [] ? null : t(count($freeing) > 1 ? 'design.by_hand.all_freed' : 'design.by_hand.freed'),
                200,
                $character,
            );
        }

        if ($state['errors'] !== []) {
            return $this->screen($state, $state['errors'], t('design.not_saved'), 422, $character);
        }

        $db = $this->db();
        /*
         * PUBLISH ASKS ONCE, when the answer is destructive (D-068, handoff §2.1).
         *
         * Applying a character to a site that already has blocks can rewrite every section's
         * style and layout, so that has always needed two explicit buttons. They used to sit
         * in the bar permanently — three buttons for a choice that matters on the rare
         * publish after loading a character — so the bar is one Publish now and the question
         * is asked at the moment it applies.
         */
        if ($action === 'save' && $character !== '' && Composition::hasBlocks($db)) {
            return $this->screen($state, [], null, 200, $character, true);
        }
        $composing = $action === 'save_composition';
        // A menu is chosen by name, and a name no menu carries any more is cleared rather
        // than stored: the header would render nothing for it, and a setting that silently
        // means nothing is worse than an empty one the owner can see.
        $goneMenu = $state['menu'] !== '' && !in_array($state['menu'], self::menuNames($db), true);
        // Each footer column's menu likewise (D-115); `header` and `none` are choices, not
        // names, and a name no menu carries is stored as none.
        $footerMenus = [];
        $goneFooterMenu = false;
        foreach ($state['footer_menus'] as $n => $name) {
            $gone = $name !== '' && $name !== SiteChrome::FOOTER_MENU_HEADER && $name !== SiteChrome::FOOTER_MENU_NONE && !in_array($name, self::menuNames($db), true);
            $goneFooterMenu = $goneFooterMenu || $gone;
            $footerMenus[$n] = $gone ? SiteChrome::FOOTER_MENU_NONE : $name;
        }

        Design::save($db, $state['decisions'], (string) $this->container->get('config')->get('app.cache_path'));
        SiteChrome::saveShared($db, $goneMenu ? '' : $state['menu'], $footerMenus);
        ChromeLook::save($db, $state['look']);
        ChromeWords::save($db, $state['words']);

        $message = t('appearance.published');
        if ($character !== '') {
            Composition::remember($db, $character);
            if ($composing) {
                $count = Composition::apply($db, $this->container->get('blocks'), $character);
                $message = t('design.saved_with_composition', [
                    'count' => $count,
                    'character' => t('design.preset.' . $character),
                ]);
            }
        }
        if ($goneMenu || $goneFooterMenu) {
            $message .= ' ' . t('chrome.menu_gone');
        }
        Activity::record($db, 'design', 'saved', null, $character !== '' ? t('design.preset.' . $character) : '');
        $session = $this->container->get('session');
        $session->set('flash', $message);
        $session->set('flash_kind', $goneMenu ? 'warning' : 'success');

        return Response::redirect(Url::admin('appearance'));
    }

    /**
     * The library (D-061): keep what is on the screen, bring one back, throw one away.
     *
     * NONE OF THE THREE TOUCHES THE SITE, and each answers with the screen rather than a
     * redirect — a redirect would hand back the PUBLISHED design, so the owner would press
     * "keep this design" and watch their work vanish from the screen it was just kept from.
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, menu: string, footer_menus?: array<int, string>, words: array<string, array<string, mixed>>, errors: array<string, string>} $state
     */
    private function library(Request $request, string $action, array $state): Response
    {
        $db = $this->db();
        $character = Presets::exists($request->input('character')) ? $request->input('character') : '';

        // Overwriting from a card in the rail: the name comes from the design itself, so
        // "save what is on screen into this one" needs no field and cannot be mistyped.
        if (str_starts_with($action, 'library:save:')) {
            $into = DesignLibrary::find($db, (int) substr($action, strlen('library:save:')));
            if ($into === null) {
                return $this->screen($state, [], null, 404, $character);
            }
            DesignLibrary::save($db, $into['name'], $state['decisions'], $state['look'], $character);
            Activity::record($db, 'design', 'kept', null, $into['name']);

            return $this->screen($state, [], t('appearance.library.overwritten', ['name' => $into['name']]), 200, $character);
        }

        if ($action === 'library:save') {
            $name = DesignLibrary::cleanName($request->input('library_name'));
            if ($name === '') {
                return $this->screen($state, ['library_name' => t('appearance.library.name_needed')], null, 422, $character);
            }
            $written = DesignLibrary::exists($db, $name);
            DesignLibrary::save($db, $name, $state['decisions'], $state['look'], $character);
            Activity::record($db, 'design', 'kept', null, $name);

            return $this->screen($state, [], t($written ? 'appearance.library.overwritten' : 'appearance.library.saved', ['name' => $name]), 200, $character);
        }

        $id = (int) substr($action, (int) strrpos($action, ':') + 1);
        $saved = DesignLibrary::find($db, $id);
        if ($saved === null) {
            return $this->screen($state, [], null, 404, $character);
        }

        if (str_starts_with($action, 'library:delete:')) {
            DesignLibrary::delete($db, $id);
            Activity::record($db, 'design', 'deleted', null, $saved['name']);

            return $this->screen($state, [], t('appearance.library.deleted', ['name' => $saved['name']]), 200, $character);
        }

        // Using one fills the screen with it: the design AND the header and footer it was
        // kept with. The menu and the words stay the site's own.
        return $this->screen(
            ['decisions' => $saved['decisions'], 'look' => $saved['look']] + $state,
            [],
            t('appearance.library.loaded', ['name' => $saved['name']]),
            200,
            $saved['character'],
        );
    }

    /**
     * @param array{decisions: array<string, string>, look: array<string, string>, menu: string, footer_menus?: array<int, string>, words: array<string, array<string, mixed>>} $state
     * @param array<string, string> $errors
     * @param string $character the character loaded into the form, if any
     * @param bool $confirm whether Publish is asking how to apply that character
     */
    private function screen(array $state, array $errors, ?string $notice, int $status = 200, string $character = '', bool $confirm = false): Response
    {
        $db = $this->db();
        $decisions = $state['decisions'];
        $byHand = Tokens::byHand($decisions);
        $colors = Palette::colors($decisions['seed'], $decisions['secondary'], $decisions['surface_contrast'], $byHand);
        $shown = Url::primaryLocale() !== '' ? Url::primaryLocale() : ($this->locales()[0] ?? 'en');

        return AdminView::render($this->container, __DIR__ . '/views', 'appearance', [
            'title' => t('appearance.title'),
            'nav' => 'appearance',
            // ORDER IS LOAD-BEARING FOR THE LAST ONE. -widths.css holds every threshold at
            // which this screen rearranges, and several of those override a base rule of the
            // same specificity in the three before it, so the cascade is decided here (D-072).
            'styles' => [
                // The rich text editor's own, first: the footer's text is rich text (D-113).
                'admin-richtext.css',
                'admin-appearance.css',
                'admin-appearance-rail.css',
                'admin-appearance-picture.css',
                'admin-appearance-inspector.css',
                'admin-appearance-colour.css',
                'admin-appearance-widths.css',
            ],
            // TipTap and the field script that binds it, the same pair the page editor loads.
            'scripts' => ['vendor/tiptap.bundle.min.js', 'richtext.js'],
            // The screen IS the window, as the page editor's canvas is: the admin's rail
            // folds to its icons beside it (D-064).
            'bare' => true,
            'decisions' => $decisions,
            'errors' => $errors,
            'notice' => $notice,
            'character' => $character,
            // Publish has asked, and the screen is waiting for which of the two it is.
            'confirm' => $confirm,
            'activeCharacter' => Composition::active($db),
            'hasBlocks' => Composition::hasBlocks($db),
            'library' => DesignLibrary::all($db),
            'colors' => $colors,
            'pairs' => Palette::pairs($colors, $decisions['secondary'] !== '', $byHand, Tokens::ownChrome($decisions)),
            'readable' => Tokens::readable($decisions),
            'readouts' => AppearanceForm::readouts($decisions),
            // The chrome half of the screen.
            'look' => $state['look'],
            'menu' => $state['menu'],
            'footerMenus' => $state['footer_menus'] ?? SiteChrome::footerMenus($db),
            'menus' => self::menuNames($db),
            'words' => $state['words'],
            'locales' => $this->container->get('locales'),
            'shownLocale' => $shown,
            // What "as the character has it" means right now, so each choice can say it.
            'characterLook' => ChromeLook::CHARACTER[$character !== '' ? $character : Composition::active($db)] ?? ChromeLook::CHARACTER['minimal'],
            // What the button may point at, per language: a Croatian header links to
            // Croatian pages (D-034).
            'linkPages' => array_combine($this->locales(), array_map(
                static fn (string $code): array => PageLinks::choices($db, $code),
                $this->locales(),
            )),
            // What the strip over the picture says is in the frame.
            'host' => (string) parse_url(Url::withOrigin(''), PHP_URL_HOST),
            'pageName' => self::previewedPage($db, $shown),
            'previewPages' => self::previewPages($db, $shown),
            'previewUrl' => Url::withQuery(Url::admin('appearance', 'preview'), AppearanceForm::query($state, $shown, $character)),
        ], $status);
    }

    /**
     * What the preview is a picture OF: the home page by name, or the specimen when a site
     * has no home page yet. The strip says so, because a preview with no address is a
     * picture of something.
     */
    private static function previewedPage(Db $db, string $locale): string
    {
        $home = $db->one('SELECT title FROM pages WHERE slug = ? AND locale = ?', ['', $locale]);

        return $home === null ? t('design.preview') : (string) $home['title'];
    }

    /**
     * The published pages the picture can be of, in the tree's order with the home page
     * first (D-111): a header laid over the first section looks different over a page with
     * no hero, and a sticky header cannot be judged on a short one.
     *
     * @return list<array{id: int, title: string, depth: int}>
     */
    private static function previewPages(Db $db, string $locale): array
    {
        $status = [];
        foreach ($db->all('SELECT id, status FROM pages WHERE locale = ?', [$locale]) as $row) {
            $status[(int) $row['id']] = (string) $row['status'];
        }
        $pages = [];
        foreach (PageTree::parentOptions($db, $locale, null) as $option) {
            if (($status[(int) $option['id']] ?? '') === 'published') {
                $pages[] = ['id' => (int) $option['id'], 'title' => (string) $option['title'], 'depth' => (int) $option['depth']];
            }
        }

        return $pages;
    }

    /**
     * The menu names on offer, each once. The same name in two languages is one choice:
     * that is the point of storing a name rather than an id.
     *
     * @return list<string>
     */
    private static function menuNames(Db $db): array
    {
        $names = [];
        foreach (Menu::all($db) as $menu) {
            $names[$menu['name']] = true;
        }

        return array_keys($names);
    }

    /**
     * array_values, because array_map over the container's locales keeps that array's keys
     * and a list is what this promises.
     *
     * @return list<string>
     */
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
