<?php

namespace App\Modules\Demo;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\Composition;
use App\Modules\Forms\Form;
use App\Modules\Languages\Locales;
use App\Modules\Pages\Page;
use App\Modules\Pages\PageSeo;
use App\Modules\Pages\PageBlocks;
use App\Modules\Pages\Translations;
use RuntimeException;

/**
 * The demo site (PLAN.md D-213): The Printworks, a community arts centre in a former printing
 * works. Ten pages and three beneath them, menus over them, and two pages translated (Home
 * and Visit), so a translation is always there to look at. One more page, not in the menu,
 * shows every block in every layout: the visual regression fixture.
 *
 * A new install can start from it, and `php migrations/seed.php` adds it to an empty site.
 */
final class DemoSite
{
    /**
     * The character the demo's pages are written against (README 1.6: the mockup at its
     * Soft-like set). A section or block stores only where it differs from this one's
     * composition, so under every other character it looks as it always has.
     */
    public const CHARACTER = 'soft';

    /**
     * The character the demo is installed with, and its header (the owner, D-216): The
     * Printworks looks best in Couture, with the logo above a centred menu.
     */
    public const SHOWN_WITH = 'couture';

    /** @var array<string, string> the demo's own look over the character's */
    public const LOOK = ['header_arrangement' => 'centred'];

    /** The demo's own name, in either language (D-213). */
    public const NAME = 'The Printworks';

    /** The pages translated into the demo's other language (the owner, D-213). */
    public const TRANSLATED = ['home', 'visit'];

    /**
     * Creates the demo in $locale: Croatian words for a Croatian site, English for any other.
     * Refuses a site that already has pages rather than mixing demo content into real content.
     *
     * $store takes the demo's pictures into the library (DemoPictures::importer); without it
     * the pages are made with their picture fields empty, as the tests make them.
     *
     * $root is where the pictures and documents are (DemoPictures): demo_images/ when empty.
     *
     * @param \Closure(string, string): int|null $store
     * @return int the number of pages created in $locale (the translation not counted)
     */
    public static function seed(Db $db, Blocks $registry, string $locale, ?\Closure $store = null, string $root = ''): int
    {
        if ((int) ($db->one('SELECT COUNT(*) AS n FROM pages')['n'] ?? 0) > 0) {
            throw new RuntimeException('The site already has pages. The demo is only added to a site without any.');
        }

        $lang = $locale === 'hr' ? 'hr' : 'en';
        $pages = self::pages($lang);
        $named = DemoPictures::named($pages);
        $pictures = $store === null ? [] : DemoPictures::import($db, $store, $locale, $lang, $named['pictures'], $root);
        $files = $store === null ? [] : DemoPictures::documents($store, $lang, $named['files'], $root);
        // Every page first, so a link can refer to one seeded after it (PLAN.md D-034).
        $ids = [];
        foreach ($pages as $page) {
            $ids[$page['key']] = Page::create($db, $registry, $locale, $page['title'], $page['slug'], null, []);
        }
        $forms = self::forms($db, $locale, $lang);
        $media = ['pictures' => $pictures, 'files' => $files, 'forms' => $forms];

        foreach ($pages as $page) {
            self::fill($db, $registry, $ids[$page['key']], $page, $ids, $media);
            Page::setStatus($db, $ids[$page['key']], true);
        }
        DemoChrome::menus($db, $locale, $lang, $pages, $ids);
        self::translate($db, $registry, $pages, $ids, $media, $lang === 'hr' ? 'en' : 'hr');
        DemoPictures::translate($db, $pictures, $lang === 'hr' ? 'en' : 'hr', $root);
        // Drawn with Couture and a centred header (D-216); its sections store only what differs
        // from Soft, so they follow Couture where they name nothing of their own (the owner's
        // review of D-170). The caller compiles the design: Installer does.
        Composition::remember($db, self::SHOWN_WITH);
        \App\Modules\Design\Design::store($db, self::LOOK + \App\Modules\Design\Design::load($db));
        // The site is the demo's, whatever the installer was told: the pages, the menu and the
        // pictures are all The Printworks', and a header naming something else read as two
        // sites in one (the copy's "Checklist Site", D-177).
        \App\Core\Settings::set($db, 'site_name', self::NAME);

        return count($pages);
    }

    /**
     * One page's sections and blocks, written as the builder writes them, under its parent
     * where it has one.
     *
     * @param array{key: string, parent?: string, slug: string, title: string, description: string, menu: string, sections: list<array{style: array<string, string>, layout: string, stack?: string, blocks: list<array{string, array<string, mixed>, string, array<string, string>, int}>}>} $page
     * @param array<string, int> $ids page key => id
     * @param array{pictures: array<string, int>, files: array<string, int>, forms: array<string, int>} $media
     */
    private static function fill(Db $db, Blocks $registry, int $id, array $page, array $ids, array $media): void
    {
        [$blocks, $sections] = DemoContent::content($registry, $page, $ids, $media);
        Page::update($db, $registry, $id, [
            'title' => $page['title'],
            'slug' => $page['slug'],
            'parent_id' => isset($page['parent']) ? ($ids[$page['parent']] ?? null) : null,
            'status' => 'draft',
            // The showroom is for looking at, not for finding (D-170).
            'seo_json' => PageSeo::json(['title' => '', 'description' => $page['description'], 'noindex' => $page['key'] === 'blocks']),
        ], $blocks, $sections);
    }

    /**
     * The demo's forms in one language (forms.php), by key: the contact form as Boxlet makes
     * a new one, the others with their own fields.
     *
     * @return array<string, int> key => form id
     */
    private static function forms(Db $db, string $locale, string $lang): array
    {
        $ids = [];
        foreach ((require __DIR__ . '/forms.php')($lang) as $key => $form) {
            $ids[$key] = Form::create($db, $locale, $form['name']);
            $made = Form::find($db, $ids[$key]);
            if ($form['fields'] !== null && $made !== null) {
                Form::update($db, $ids[$key], $form['name'], $form['fields'], $made['settings']);
            }
        }

        return $ids;
    }

    /**
     * Home and Visit in the other of the demo's two languages, so the site always has a
     * translation to show: the language added, each page translated as the owner would, its
     * words, pictures and form that language's, and menus over what was translated.
     *
     * @param list<array{key: string, parent?: string, slug: string, title: string, description: string, menu: string, sections: list<array{style: array<string, string>, layout: string, stack?: string, blocks: list<array{string, array<string, mixed>, string, array<string, string>, int}>}>}> $pages in the site's own language
     * @param array<string, int> $ids page key => id, in the site's own language
     * @param array{pictures: array<string, int>, files: array<string, int>, forms: array<string, int>} $media
     */
    private static function translate(Db $db, Blocks $registry, array $pages, array $ids, array $media, string $other): void
    {
        if ($db->one('SELECT code FROM locales WHERE code = ?', [$other]) === null) {
            Locales::add($db, $other);
        }
        // The other language's own forms: a form belongs to one language (Form::choices).
        $media['forms'] = self::forms($db, $other, $other);
        $theirs = [];
        foreach (self::pages($other) as $page) {
            $theirs[$page['key']] = $page;
        }
        $translated = [];
        foreach (self::TRANSLATED as $key) {
            $id = Translations::create($db, $registry, $ids[$key], $other);
            if (!is_int($id)) {
                continue;
            }
            // Same sections, same blocks, same order: only the words differ, so each copied
            // block keeps its place and its tie to the source and takes its new words by
            // position. Its links lead to the site's own pages: those are not translated.
            [$words, $styles] = DemoContent::content($registry, $theirs[$key], $ids, $media);
            $blocks = PageBlocks::editable($db, $registry, $id);
            foreach ($blocks as $at => $block) {
                if (($words[$at]['type'] ?? null) === $block['type']) {
                    $blocks[$at]['content'] = $words[$at]['content'];
                }
            }
            // And each section's name and anchor in that language: the copy kept the source's.
            $sections = PageBlocks::editableSections($db, $id);
            foreach ($sections as $at => $section) {
                foreach (['name', 'anchor'] as $own) {
                    $sections[$at]['style'][$own] = (string) ($styles[$at]['style'][$own] ?? '');
                }
            }
            Page::update($db, $registry, $id, [
                'title' => $theirs[$key]['title'],
                'slug' => $theirs[$key]['slug'],
                'parent_id' => null,
                'status' => 'published',
                'seo_json' => PageSeo::json(['title' => '', 'description' => $theirs[$key]['description']]),
            ], $blocks, $sections);
            $translated[$key] = $id;
        }
        DemoChrome::translatedMenus($db, $other, $pages, $translated);
    }

    /**
     * The demo's pages in one of its two languages.
     *
     * @return list<array{key: string, parent?: string, slug: string, title: string, description: string, menu: string, sections: list<array{style: array<string, string>, layout: string, stack?: string, blocks: list<array{string, array<string, mixed>, string, array<string, string>, int}>}>}>
     */
    public static function pages(string $lang = 'en'): array
    {
        return (require __DIR__ . '/pages.php')($lang === 'hr' ? 'hr' : 'en');
    }
}
