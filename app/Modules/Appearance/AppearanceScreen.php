<?php

namespace App\Modules\Appearance;

use App\Core\Container;
use App\Core\Db;
use App\Core\Response;
use App\Modules\Admin\AdminView;
use App\Modules\Design\Characters;
use App\Modules\Design\Composition;
use App\Modules\Design\Design;
use App\Modules\Design\Palette;
use App\Modules\Design\PalettePairs;
use App\Modules\Design\Vocabulary\Decisions;
use App\Modules\Design\Tokens;
use App\Modules\Pages\PageTree;
use App\Modules\Settings\ChromeLook;
use App\Support\Url;

/**
 * THE APPEARANCE SCREEN AS DRAWN (PLAN.md D-059, D-157): everything its view needs, worked
 * out from one state of the form.
 *
 * Split from AppearanceController, which had grown to 444 lines holding three things: what
 * the screen shows, what its buttons do, and publishing. This is the first. Every answer the
 * screen gives — a character loaded, a reset, a refused publish, an import waiting — is this
 * one drawing of a different state, which is why the controller and the transfer controller
 * both hand their state here rather than render a view of their own.
 */
final class AppearanceScreen
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * What the site is published with: the state the screen opens on.
     *
     * @return array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>}
     */
    public function published(): array
    {
        $db = $this->db();

        $values = Design::load($db);
        $look = array_intersect_key($values, array_flip(ChromeLook::keys()));

        return [
            'decisions' => array_diff_key($values, $look),
            'look' => $look,
            'dark' => Design::dark($db),
        ];
    }

    /**
     * The published screen with what became of a design file brought in: the set and its
     * warnings, waiting for an answer, or the reasons it was refused (D-152).
     *
     * @param array{set: array<string, mixed>, warnings: list<string>}|null $import
     * @param list<string> $importErrors
     */
    public function withImport(?array $import, array $importErrors): Response
    {
        return $this->render($this->published(), [], null, $importErrors === [] ? 200 : 422, '', ['import' => $import, 'importErrors' => $importErrors]);
    }

    /**
     * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>} $state
     * @param array<string, string> $errors
     * @param string $character the character loaded into the form, if any
     * @param array{confirm?: bool, restyled?: int, ownLayouts?: int, import?: array{set: array<string, mixed>, warnings: list<string>}|null, importErrors?: list<string>} $extra
     *        a question waiting for an answer: how to publish a character (confirm), what
     *        to do with an imported file
     */
    public function render(array $state, array $errors, ?string $notice, int $status = 200, string $character = '', array $extra = []): Response
    {
        $db = $this->db();
        $active = Composition::active($db);
        // WHAT THE SCREEN MEASURES AGAINST (D-158): the character loaded into it, else the one
        // the site was composed with. Every dot, count and reset on the screen reads this one.
        $basis = $character !== '' ? $character : $active;
        $values = $state['decisions'] + $state['look'];
        $dark = Tokens::darkOwn($state['dark'] ?? []);
        [$resolved, $defaults, $shown] = self::shown($values, $basis, $dark);
        $published = Design::load($db);
        $colors = Palette::forDecisions($resolved);
        $pairs = PalettePairs::pairs($colors, $resolved['secondary'] !== '', Tokens::byHand($resolved), Tokens::ownChrome($resolved));
        $shownLocale = Url::primaryLocale() !== '' ? Url::primaryLocale() : ($this->locales()[0] ?? 'en');
        $lookKeys = array_flip(ChromeLook::keys());
        $characterLook = Characters::look($basis);

        return AdminView::render($this->container, __DIR__ . '/views', 'appearance', [
            'title' => t('appearance.title'),
            'nav' => 'appearance',
            // ORDER IS LOAD-BEARING FOR THE LAST ONE. -widths.css holds every threshold at
            // which this screen rearranges, and several of those override a base rule of the
            // same specificity in the files before it, so the cascade is decided here (D-072).
            'styles' => [
                'admin-appearance.css',
                'admin-appearance-home.css',
                'admin-appearance-tiles.css',
                'admin-appearance-picture.css',
                'admin-appearance-sections.css',
                'admin-appearance-inspector.css',
                'admin-appearance-fonts.css',
                'admin-appearance-colour.css',
                'admin-appearance-contrast.css',
                'admin-appearance-layout.css',
                'admin-appearance-widths.css',
            ],
            // A design file is sent when it is chosen (file-sends.js, D-152). The footer's rich
            // text, and TipTap with it, went to Navigation (D-180).
            'scripts' => ['file-sends.js'],
            // The screen IS the window, as the page editor's canvas is: the admin's rail
            // folds to its icons beside it (D-064).
            'bare' => true,
            // Every key as shown; the look's half is also handed on its own, as the header and
            // footer sections read it.
            'decisions' => array_diff_key($shown, $lookKeys),
            'errors' => $errors,
            'notice' => $notice,
            'character' => $character,
            'basis' => $basis,
            'confirm' => $extra['confirm'] ?? false,
            // The header's and footer's blocks, whose layouts draw their arrangements (D-166).
            'chromeBlocks' => $this->container->get('chrome'),
            'restyled' => $extra['restyled'] ?? 0,
            // Blocks with a layout of the owner's own, which Apply keeps (D-191): said beside.
            'ownLayouts' => $extra['ownLayouts'] ?? 0,
            'activeCharacter' => $active,
            'hasBlocks' => Composition::hasBlocks($db),
            'library' => DesignLibrary::all($db),
            'colors' => $colors,
            // THE OWNER'S DARK COLOURS (D-187): what each is, '' for none, and what dark mode
            // shows where there is none — the colour held for both modes, else the
            // character's dark version, else the palette's.
            'dark' => $dark,
            'darkColors' => Palette::forDecisions(Tokens::resolve(['mode' => 'dark'] + $values, $basis, $dark)),
            'pairs' => $pairs,
            'readable' => Tokens::readable($resolved),
            'readouts' => AppearanceForm::readouts($shown) + SectionSummaries::of($resolved, $pairs),
            // What the owner has made theirs over that character, and what each control is
            // when they have not (D-158).
            'defaults' => $defaults,
            // The keys that follow a family but the character pins (D-185): a family chosen
            // does not move them.
            'pinned' => array_keys(array_filter(
                array_intersect_key(\App\Modules\Design\Characters::decisions($basis), array_flip(['heading_weight', 'tracking', 'caps', 'line_height'])),
                static fn (string $value): bool => $value !== '',
            )),
            // The owner's dark colours (D-187) as `dark_<key>`: each its own dot, and counted.
            'changed' => array_merge(Overrides::changed($values, $basis), array_map(static fn (string $key): string => 'dark_' . $key, array_keys(array_filter($dark, static fn (string $v): bool => $v !== '')))),
            // The chrome half of the screen, as shown.
            'look' => array_intersect_key($shown, $lookKeys),
            'locales' => $this->container->get('locales'),
            'shownLocale' => $shownLocale,
            'characterLook' => $characterLook,
            // What the strip over the picture says is in the frame.
            'host' => (string) parse_url(Url::withOrigin(''), PHP_URL_HOST),
            'pageName' => self::previewedPage($db, $shownLocale),
            'previewPages' => self::previewPages($db, $shownLocale),
            // What the published site's controls show, by field (D-181): the bar counts
            // the keys the screen differs on, a character loaded included.
            'keywords' => self::keywords(),
            'publishedFields' => AppearanceForm::fields(self::shown($published, $active)[2], Design::dark($db)),
            'previewUrl' => Url::withQuery(Url::admin('appearance', 'preview'), AppearanceForm::query($state, $shownLocale, $character, $basis)),
            // Design files (D-152): one brought in and waiting, why one was refused, the custom
            // files left out, and a character the site was composed with that is gone (D-156).
            'import' => $extra['import'] ?? null,
            'importErrors' => $extra['importErrors'] ?? [],
            'skipped' => Characters::skipped(),
            'missingCharacter' => Composition::missing($db),
        ], $status);
    }

    /**
     * The words a control is found by besides its own (D-181): `appearance.keywords.<key>`,
     * where there are any.
     *
     * @return array<string, string>
     */
    private static function keywords(): array
    {
        $words = [];
        foreach (Overrides::SECTIONS as $groups) {
            foreach ($groups as $keys) {
                foreach ($keys as $key) {
                    $said = t('appearance.keywords.' . $key);
                    if ($said !== 'appearance.keywords.' . $key) {
                        $words[$key] = $said;
                    }
                }
            }
        }

        return $words;
    }

    /**
     * What every control SHOWS (D-164): the design as drawn, and for a key that follows the
     * typeface the pairing's own value, so a slider nobody moved stands where the page is.
     *
     * @param array<string, string> $values
     * @param array<string, string> $dark the owner's dark colours
     * @return array{0: array<string, string>, 1: array<string, string>, 2: array<string, string>} resolved, defaults, shown
     */
    private static function shown(array $values, string $basis, array $dark = []): array
    {
        $resolved = Tokens::resolve($values, $basis, $dark);
        $defaults = Overrides::defaults($basis, $resolved['heading_font'], $resolved['body_font']);
        $shown = $resolved;
        // THE CONTROLS ARE THE LIGHT VERSION'S, WHATEVER THE MODE (D-193, found with the new sets).
        // In dark mode the design as drawn has the character's dark version in it; shown in the
        // controls, a Publish then stored Workshop's dark page colour as the owner's for both
        // modes, and every character after it kept it. The dark colours have their own rows.
        if (($resolved['mode'] ?? '') === 'dark') {
            $light = Tokens::resolve(['mode' => 'light'] + $values, $basis);
            foreach (Decisions::DARK as $key) {
                $shown[$key] = $light[$key];
            }
        }
        foreach ($shown as $key => $value) {
            if ($value === '' && Decisions::follows($key) === 'font') {
                $shown[$key] = $defaults[$key];
            }
        }

        return [$resolved, $defaults, $shown];
    }

    /**
     * array_values, because array_map over the container's locales keeps that array's keys
     * and a list is what this promises.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values(array_map(
            static fn (array $locale): string => (string) $locale['code'],
            $this->container->get('locales'),
        ));
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

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
