<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Admin\AdminView;
use App\Modules\Design\Characters;
use App\Modules\Design\Composition;
use App\Support\Dates;
use App\Support\Url;

/**
 * THE PAGE BUILDER (PLAN.md D-163, D-175, README 4): a canvas showing the real page, a rail of
 * Structure, Add and Page, and an inspector for whatever is selected.
 *
 * THE DOCUMENT LIVES IN THE BROWSER. The shell hands it over once, with everything the browser
 * needs to work on it — the blocks it can add, the patterns, the page's history, the addresses
 * it talks to — and from then on builder-*.js keeps it: every change writes the document, the
 * autosave sends it to /draft, and what only the server can do it asks of PageBuilderApi
 * (draw a band, draw the inspector, clean a block's fields).
 *
 * The canvas is an iframe of the same origin rendering the page exactly as a visitor gets it
 * (PageRender), so site CSS and admin CSS cannot collide and what you see is the page. The
 * builder's own controls are drawn into it from the parent; nothing is wrapped around a block.
 */
final class PageBuilderController
{
    /**
     * The order blocks are offered in, in Add and in the quick inserter (the owner, D-176):
     * what a page is made of most, first. A block not named here comes after, by name.
     */
    public const ORDER = ['text', 'hero', 'image_text', 'cards', 'quote', 'cta', 'picture', 'gallery', 'stats', 'accordion', 'logos', 'downloads', 'form', 'embed', 'divider'];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) $params['id']);
        $current = $page === null ? null : PageDraft::current($this->db(), $this->registry(), (int) $page['id']);
        if ($page === null || $current === null) {
            return PagesController::missing();
        }
        $id = (int) $page['id'];
        $character = Composition::active($this->db());
        $state = PageDraft::state($this->db(), $page);
        $library = $this->library((string) $page['locale']);

        return AdminView::render($this->container, __DIR__ . '/views', 'admin/builder', [
            'title' => t('pages.edit'),
            'nav' => 'pages',
            'styles' => ['admin-richtext.css', 'admin-richtext-tags.css', 'admin-media.css', 'admin-picker.css', 'admin-browser.css', 'vendor/cropper.min.css', 'admin-crop.css', 'builder.css', 'builder-narrow.css', 'builder-rail.css', 'builder-add.css', 'builder-inspector.css'],
            'scripts' => ['vendor/tiptap.bundle.min.js', 'richtext-link.js', 'richtext.js', 'vendor/cropper.min.js', 'media-browser-upload.js', 'media-browser.js', 'media-picker.js', 'repeater.js', 'vendor/sortable.min.js'],
            'wide' => true,
            'bare' => true,
            'page' => $page,
            'document' => $current['document'],
            'state' => $state,
            'registry' => $this->registry(),
            'character' => $character,
            'parents' => PageTree::parents($this->db(), (string) $page['locale'], $id),
            // Each possible parent's address, so the Page tab shows the whole address live.
            'parentUrls' => $this->parentUrls((string) $page['locale']),
            'siteName' => \App\Core\Settings::text($this->db(), 'site_name'),
            // The pictures the page's own sharing picture is chosen from (D-242).
            'sharePictures' => \App\Modules\Media\MediaReference::choices($this->db()),
            'languages' => $this->languages($id, (string) $page['locale']),
            'library' => $library,
            'patterns' => [
                'mine' => PagePattern::mine($this->db()),
                'set' => PagePattern::fromSet($character, (string) $page['locale']),
                'setName' => Characters::label($character),
            ],
            'revisions' => PageRevision::all($this->db(), $id),
            'translation' => TranslationStatus::described($this->db(), $this->registry(), $id),
            'zone' => Dates::zone($this->db()),
            'data' => [
                'page' => ['id' => $id, 'locale' => (string) $page['locale'], 'state' => $state, 'url' => Url::page((string) $page['locale'], (string) $page['slug'])],
                'version' => $current['version'],
                'document' => $current['document'],
                'library' => $library,
                // Each block type's layouts by name, for the canvas toolbar's dropdown, and the
                // one the character composes, which a block following it ('') is drawn in (D-191).
                'layouts' => $this->layouts(),
                'composed' => array_combine($this->registry()->types(), array_map(fn (string $type): string => Composition::layout($this->registry(), $character, $type), $this->registry()->types())),
                'setPatterns' => PagePattern::fromSet($character, (string) $page['locale']),
                'setName' => Characters::label($character),
                // Typing on the page (D-178): what each field is, a repeater's new item, the pages.
                'inline' => InlineFields::of($this->db(), $this->registry(), (string) $page['locale']),
                'endpoints' => [
                    'draft' => Url::admin('pages', $id, 'draft'),
                    'render' => Url::admin('pages', $id, 'render'),
                    'inspect' => Url::admin('pages', $id, 'inspect'),
                    'fields' => Url::admin('pages', $id, 'fields'),
                    'publish' => Url::admin('pages', $id, 'publish'),
                    'discard' => Url::admin('pages', $id, 'discard'),
                    'restore' => Url::admin('pages', $id, 'restore'),
                    'patterns' => Url::admin('patterns'),
                ],
                'strings' => self::strings(),
            ],
            // What a field in the inspector offers: the same lists the plain editor's have.
            'pictures' => \App\Modules\Media\MediaReference::choices($this->db()),
            'files' => \App\Modules\Media\MediaFiles::choices($this->db()),
            'formChoices' => \App\Modules\Forms\Form::choices($this->db(), (string) $page['locale']),
            'linkPages' => PageLinks::choices($this->db(), (string) $page['locale']),
        ]);
    }

    /**
     * The page itself, for the canvas iframe: the draft when there is one, drawn by the
     * visitor's own drawing with the editor's marks (PageRender), with the same stylesheets.
     *
     * @param array<string, string> $params
     */
    public function canvas(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) $params['id']);
        $current = $page === null ? null : PageDraft::current($this->db(), $this->registry(), (int) $page['id']);
        if ($page === null || $current === null) {
            return PagesController::missing();
        }
        // A translation's blocks that have fallen behind their source are marked on their band
        // (D-043, step 3); only stored blocks can be.
        $stale = TranslationStatus::of($this->db(), $this->registry(), (int) $page['id'])['stale'];
        $html = PageRender::draw(
            $this->db(),
            $this->registry(),
            $current['document'],
            (string) $page['locale'],
            Composition::active($this->db()),
            (string) $this->container->get('config')->get('app.key'),
            ['editor' => true, 'stale' => $stale],
        )['html'];

        $body = (new View(__DIR__ . '/views'))->render('admin/canvas', $locale, [
            'title' => (string) $page['title'],
            'blocksHtml' => $html,
        ], null);

        $response = Response::admin($body);
        // The one admin document that may be framed, and only by the admin itself. One frame
        // of another site's may show: an OpenStreetMap map, the only embed drawn before a press
        // (D-147), which cannot be pressed here (canvas.css, D-148).
        $response->headers['Content-Security-Policy'] = "default-src 'self'; img-src 'self' data:; "
            . "frame-src https://www.openstreetmap.org; form-action 'none'; frame-ancestors 'self'; base-uri 'none'";
        $response->headers['X-Frame-Options'] = 'SAMEORIGIN';

        return $response;
    }

    /**
     * The words builder-*.js shows, from the admin's language files: the scripts are not
     * translated, so they are handed what they say.
     *
     * @return array<string, string>
     */
    private static function strings(): array
    {
        $keys = [
            'save.saving', 'save.saved', 'save.failed', 'save.conflict', 'save.reload', 'save.idle', 'publishing', 'published', 'publish_refused',
            'discard_confirm', 'structure.sections', 'structure.blocks', 'structure.column_name', 'structure.empty', 'add.where_end', 'add.where_after', 'add.none_found',
            'canvas.add_here', 'canvas.add_end', 'canvas.add_block', 'canvas.add_block_title', 'canvas.insert_title', 'canvas.close', 'canvas.hidden_here', 'canvas.move_up', 'canvas.move_down',
            'canvas.copy', 'canvas.delete', 'canvas.layout', 'layout_default', 'canvas.page', 'canvas.empty', 'section_n', 'pattern_name', 'pattern_saved', 'delete_confirm',
            'page.restored', 'device.desktop', 'device.tablet', 'device.phone', 'add.mine', 'add.none_mine', 'add.from_set', 'inline.remove_item', 'inline.item_before', 'inline.item_after', 'inline.link_title', 'inline.link_text', 'inline.link_page', 'inline.link_other', 'inline.link_url', 'inline.link_done', 'inline.link_remove',
        ];
        $strings = [];
        foreach ($keys as $key) {
            $strings[$key] = t('builder.' . $key);
        }
        // The forms a count takes (D-240), each the one its sample number takes in the admin's
        // language: builder-tree.js asks for structure.sections_few where boxletPlural() says
        // few, and a language with no few form hands it the general one.
        foreach (['structure.sections', 'structure.blocks'] as $key) {
            $strings[$key . '_one'] = t(\App\Support\AdminLang::countedKey('builder.' . $key, 1));
            $strings[$key . '_few'] = t(\App\Support\AdminLang::countedKey('builder.' . $key, 2));
        }
        // The rich text editor's own words, for its toolbar on the page (D-178): the same
        // words the inspector's toolbar says.
        foreach (['bold', 'italic', 'link', 'unlink', 'heading_2', 'heading_3', 'quote', 'bullets', 'numbers', 'toolbar', 'align_left', 'align_center', 'align_right'] as $key) {
            $strings['rt.' . $key] = t('richtext.' . $key);
        }
        foreach (['published', 'changes', 'draft'] as $state) {
            $strings['state.' . $state] = t('pages.state.' . $state);
        }

        return $strings;
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function layouts(): array
    {
        $layouts = [];
        foreach ($this->registry()->types() as $type) {
            foreach ($this->registry()->get($type)['layouts'] as $layout) {
                $layouts[$type][] = ['value' => $layout, 'label' => t('block.' . $type . '.layout.' . $layout)];
            }
        }

        return $layouts;
    }

    /**
     * Every page's address in this language, by id: what a page placed under it is addressed
     * under (D-129).
     *
     * @return array<int, string>
     */
    private function parentUrls(string $locale): array
    {
        $urls = [];
        foreach ($this->db()->all('SELECT id, slug FROM pages WHERE locale = ?', [$locale]) as $row) {
            $urls[(int) $row['id']] = Url::page($locale, (string) $row['slug']);
        }

        return $urls;
    }

    /**
     * The site's languages as the builder's language menu offers them: this page's version in
     * each, or null where there is none yet (D-043).
     *
     * @return list<array{code: string, label: string, page: int|null, current: bool}>
     */
    private function languages(int $id, string $current): array
    {
        $versions = Translations::of($this->db(), $id);
        $languages = [];
        foreach ($this->container->get('locales') as $language) {
            $code = (string) $language['code'];
            $languages[] = ['code' => $code, 'label' => (string) $language['label'], 'page' => $versions[$code] ?? null, 'current' => $code === $current];
        }

        return $languages;
    }

    /**
     * Every block that can be added: its name, its icon, a line about what it is for (D-104)
     * — no live picture of it (README 4.2) — and what a new one starts as: its fresh content,
     * following the character's layout ('', D-191).
     *
     * @return list<array{type: string, label: string, icon: string, group: string, summary: string, fresh: array<string, mixed>, layout: string}>
     */
    private function library(string $locale): array
    {
        $registry = $this->registry();
        $types = $registry->types();
        $order = array_flip(self::ORDER);
        usort($types, static fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b));
        $library = [];
        foreach ($types as $type) {
            $library[] = [
                'type' => $type,
                'label' => t('block.' . $type),
                'icon' => (string) $registry->get($type)['icon'],
                'group' => (string) $registry->get($type)['group'],
                'summary' => t('block.' . $type . '.summary'),
                // A new block says what each part is for, in the page's language (D-176).
                'fresh' => $registry->sampled($type, static fn (string $key): string => site_t($key, $locale, 'samples')),
                // A new block follows the character (D-191, the owner).
                'layout' => '',
            ];
        }

        return $library;
    }

    private function registry(): Blocks
    {
        return $this->container->get('blocks');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
