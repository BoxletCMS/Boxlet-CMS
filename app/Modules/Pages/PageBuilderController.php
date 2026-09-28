<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Admin\AdminView;
use App\Modules\Design\Composition;
use App\Modules\Design\Design;
use App\Modules\Design\SectionStyle;
use App\Modules\Forms\FormBlocks;
use App\Modules\Media\MediaPicture;
use App\Modules\Media\MediaReference;
use App\Support\Url;

// PageTree supplies the parents a page may have; it excludes the page and its own
// descendants, which is what keeps a cycle out of the hierarchy.

// BlockForm cleans a block's submitted values; the canvas re-draws from the cleaned
// ones so it shows what a save would store.

/**
 * The visual page editor: a canvas showing the real page, with the block's own fields
 * beside it.
 *
 * The canvas is an iframe rendering the page exactly as a visitor gets it, so site CSS
 * and admin CSS cannot collide and what you see is what the page is. Blocks are not
 * wrapped in editor markup: sections.css styles a section by its position among its
 * siblings, so anything inserted between them would change the page being judged.
 * Selection is a class, and the insertion controls are an overlay.
 *
 * The form is the one from Slice 3, unchanged: every block's fields are real inputs in
 * one form, submitted by an explicit Save to the same endpoint and validated by the same
 * server-side code. The canvas decides which of those groups is on screen.
 */
final class PageBuilderController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) $params['id']);
        if ($page === null) {
            return PagesController::missing();
        }

        return $this->shell(
            $page,
            (string) $page['title'],
            (string) $page['slug'],
            Page::editable($this->db(), $this->registry(), (int) $page['id']),
            null,
            [],
            null,
            200,
            true,
        );
    }

    /**
     * The page itself, for the canvas iframe: the same blocks, the same stylesheets and
     * the same markup the front end renders, plus the editor's own overlay.
     *
     * @param array<string, string> $params
     */
    public function canvas(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) $params['id']);
        if ($page === null) {
            return PagesController::missing();
        }

        $registry = $this->registry();
        ['blocks' => $blocks, 'sections' => $sections] = $this->canvasState((int) $page['id']);
        // Every picture the page refers to, in one query, before any block draws: a
        // template is handed what it needs and never touches a database.
        $media = MediaPicture::forBlocks($this->db(), $registry, $locale, $blocks);
        // Links to pages followed the way a visitor's page follows them (PLAN.md D-034), so
        // a link to a draft is missing here exactly as it will be on the site.
        $links = PageLinks::targets($this->db(), $registry, (string) $page['locale'], $blocks);
        // Forms drawn as a visitor sees them (D-046); the canvas's CSP stops a send.
        $forms = FormBlocks::resolve($this->db(), $blocks, (string) $page['locale'], null, (string) $this->container->get('config')->get('app.key'));
        // And the files a Downloads block offers (D-127), as the page gets them.
        $files = \App\Modules\Media\MediaFiles::forBlocks($this->db(), $registry, $blocks);

        // A translation's blocks that have fallen behind their source are marked on the
        // section itself (D-043, step 3); canvas.css draws the mark, builder-blocks.js keeps
        // it through a redraw. Only stored blocks can be stale, so a pending canvas has none.
        $stale = TranslationStatus::of($this->db(), $registry, (int) $page['id'])['stale'];

        /*
         * THE SAME LOOP THE FRONT END RUNS (PageController::show), and deliberately so.
         * Until D-098 this drew each block as its own band while the visitor's page drew
         * sections of columns, and the two agreed only because every section held one
         * block. The moment an author gives a section two columns they would part, and the
         * editor would be showing a page that does not exist.
         */
        $html = '';
        $first = true;
        foreach (Sections::group($this->sectionsByKey($sections), $blocks, true) as $group) {
            $drawable = [];
            $isStale = false;
            foreach ($group['blocks'] as $block) {
                // A block whose type this installation no longer has keeps its stored
                // content and simply does not draw.
                if ($block['content'] === null || !$registry->has($block['type'])) {
                    continue;
                }
                $block['content'] = PageLinks::content($registry, $block['type'], $block['content'], $links);
                $drawable[] = $block;
                $isStale = $isStale || ($block['id'] !== null && isset($stale[$block['id']]));
            }
            // A band whose every block is of a type this install no longer has draws as an
            // empty one here rather than not at all: in the editor an empty band is a thing
            // you are about to fill, and on the page it is nothing (Sections::group).
            if ($drawable === [] && $group['blocks'] !== []) {
                continue;
            }
            // ALWAYS AS COLUMNS HERE (D-103): a column is what a block is dragged into and
            // what the + in it adds to, and a band that draws none has neither.
            $drawn = SectionRender::draw($registry, $group['section'], $drawable, $media, $first, ['forms' => $forms, 'files' => $files], (string) $page['locale'], true);
            /* THE BAND SAYS WHICH BAND IT IS, for the editor only (D-099). The canvas draws
               the visitor's markup and this is the one thing added to it: without a name on
               the band, the + in an empty column has no way to say which column of which
               section it is aiming at, and the editor would be back to counting positions —
               which is what D-094 and D-098 took out of every other part of this. */
            $drawn = (string) preg_replace(
                '~^(\s*<section)\b~',
                '$1 data-bx-section="' . e((string) $group['id']) . '"',
                $drawn,
                1,
            );
            // THE BAND IS MARKED, not the block inside it (D-043 step 3). While a section
            // holds one block those are the same element and nothing changes; when it holds
            // several, "this translation has fallen behind" is a thing to say about the band
            // an author is looking at, and picking one of several identical-looking wrappers
            // out of rendered markup by position is the kind of guess that goes wrong quietly.
            if ($isStale) {
                $drawn = (string) preg_replace('~^(\s*<section)\b~', '$1 data-bx-stale', $drawn, 1);
            }
            $html .= $drawn;
            $first = false;
        }

        $body = (new View(__DIR__ . '/views'))->render('admin/canvas', $locale, [
            'title' => (string) $page['title'],
            'blocksHtml' => $html,
        ], null);

        $response = Response::admin($body);
        // The one admin document that may be framed, and only by the admin itself.
        $response->headers['Content-Security-Policy'] = "default-src 'self'; img-src 'self' data:; "
            . "form-action 'none'; frame-ancestors 'self'; base-uri 'none'";
        $response->headers['X-Frame-Options'] = 'SAMEORIGIN';

        return $response;
    }

    /**
     * The builder re-rendered with what the form submitted and nothing saved — a repeater
     * control pressed in the panel without JavaScript (PLAN.md O-11).
     *
     * The canvas reloads when this renders and draws from the database, which does not
     * hold these blocks: without the stash the item just added would be missing from the
     * page while its fields sat filled in beside it. The same mechanism rejected() uses
     * below, without the error posture — nothing here failed.
     *
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     */
    public function again(array $page, string $title, string $slug, array $blocks, ?array $sections = null): Response
    {
        $this->container->get('session')->set('pending_canvas', [
            'page' => (int) $page['id'],
            'blocks' => $blocks,
            // The arrangement goes with them (D-098). Without it a submit from the panel
            // would redraw a page of columns as a stack of full-width bands, and the author
            // would watch their arrangement apparently fall apart under an ordinary press.
            'sections' => $sections,
        ]);

        return $this->shell($page, $title, $slug, $blocks, $sections);
    }

    /**
     * Re-renders the builder after a save that did not validate, so the user stays in the
     * editor they were using. PageEditorController calls this; the parsing, validation
     * and storage it runs first are the same for both editors.
     *
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @param array<string, string> $errors
     */
    public function rejected(array $page, string $title, string $slug, array $blocks, ?array $sections, array $errors, ?string $notice): Response
    {
        // The canvas reloads when this renders, and it reads the database — which is
        // exactly what was NOT written. Without this, a rejected save appears to empty
        // the page while the fields are still full. Read once, by the next canvas.
        $this->container->get('session')->set('pending_canvas', [
            'page' => (int) $page['id'],
            'blocks' => $blocks,
            'sections' => $sections,
        ]);

        return $this->shell($page, $title, $slug, $blocks, $sections, $errors, $notice, 422);
    }

    /**
     * What the canvas should draw: normally the stored page, but after a save that did
     * not validate, the blocks as they were submitted.
     *
     * READ ONCE, both halves together: pending_canvas is removed as it is read, so asking
     * for the blocks and then for the sections would get the arrangement of the stored page
     * with the blocks of the refused save — every block homeless, every band gone.
     *
     * @return array{blocks: list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section: string, column: int}>, sections: list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>}
     */
    private function canvasState(int $pageId): array
    {
        $session = $this->container->get('session');
        $pending = $session->get('pending_canvas');
        $session->remove('pending_canvas');

        if (!is_array($pending) || ($pending['page'] ?? null) !== $pageId || !is_array($pending['blocks'] ?? null)) {
            return [
                'blocks' => Page::editable($this->db(), $this->registry(), $pageId),
                'sections' => Page::editableSections($this->db(), $pageId),
            ];
        }

        // Session data is rebuilt rather than trusted: it survives across requests, and
        // what it holds has to satisfy the same shape a stored block does.
        $blocks = [];
        foreach ($pending['blocks'] as $block) {
            if (!is_array($block) || !is_string($block['type'] ?? null)) {
                continue;
            }
            $content = $block['content'] ?? null;
            $blocks[] = [
                /* THE KEY THE SAVE WAS SUBMITTED UNDER, because the form that comes back
                   with it names its groups by the same keys, and the canvas pairs with the
                   form by key alone (PLAN.md D-117). A fresh n0, n1 … here named the canvas
                   one way and the panel another. The id is still dropped: this is drawn,
                   never saved. */
                'key' => is_string($block['key'] ?? null) && preg_match(BlockForm::KEY, $block['key']) === 1
                    ? $block['key']
                    : BlockForm::key(null, count($blocks)),
                'id' => null,
                'type' => $block['type'],
                'content' => is_array($content) ? $content : null,
                'style' => SectionStyle::normalize($block['style'] ?? null),
                'layout' => is_string($block['layout'] ?? null) ? $block['layout'] : '',
                // Where it stood when the save was refused (D-098). Without this the canvas
                // would redraw a page of columns as a stack of bands at the exact moment
                // the author is being told to fix something, and the arrangement would look
                // like the thing that had gone wrong.
                'section' => is_string($block['section'] ?? null) ? $block['section'] : SectionForm::key(null, count($blocks)),
                'column' => is_int($block['column'] ?? null) ? $block['column'] : 0,
            ];
        }

        // The arrangement as it was submitted, or the page's own when the refused save said
        // nothing about it — the same rule Page::update() follows, for the same reason.
        $sections = is_array($pending['sections'] ?? null)
            ? SectionForm::parse($pending['sections'], [])
            : Page::editableSections($this->db(), $pageId);

        /*
         * AND A BAND FOR ANY BLOCK LEFT WITHOUT ONE.
         *
         * A refused save whose body carried no sections leaves blocks naming `m0`, `m1` …
         * beside the STORED sections, which are named `s7`, `s8` … Nothing joins, and
         * Sections::group() drops a block whose section has vanished — correct on the front
         * end, catastrophic here: the canvas would come back empty at the exact moment the
         * author is being told to fix something, and it would read as the editor having
         * eaten their work. It did, once, and a test caught it.
         *
         * Each homeless block gets a one-column band carrying its own style, which is what
         * a page of blocks with nothing said about its sections has always meant.
         */
        $known = [];
        foreach ($sections as $section) {
            $known[$section['key']] = true;
        }
        foreach ($blocks as $block) {
            if (isset($known[$block['section']])) {
                continue;
            }
            $known[$block['section']] = true;
            $sections[] = [
                'key' => $block['section'],
                'id' => null,
                'layout' => SectionLayout::ONE,
                'stack' => SectionLayout::DEFAULT_STACK,
                'style' => $block['style'],
            ];
        }

        return ['blocks' => $blocks, 'sections' => $sections];
    }

    /**
     * The sections a drawing joins its blocks to, by KEY (D-098, Sections::group).
     *
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}> $sections
     * @return array<string, array{layout: string, stack: string, style: array<string, string|int|null>}>
     */
    private function sectionsByKey(array $sections): array
    {
        $byKey = [];
        foreach ($sections as $section) {
            $byKey[$section['key']] = [
                'layout' => SectionLayout::normalize($section['layout']),
                'stack' => SectionLayout::normalizeStack($section['stack']),
                'style' => SectionStyle::normalize($section['style']),
            ];
        }

        return $byKey;
    }

    /**
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @param array<string, string> $errors
     * @param bool $fromStorage whether $blocks are the page as STORED. It defaults to
     *        false because the unsafe answer must be the default: builder-save.js lets an
     *        untouched block send a skeleton instead of its fields, and the server then
     *        restores it from storage — which is right only if what is on screen came from
     *        storage in the first place. After a rejected save it did not, and a block the
     *        author edited but did not touch again would be rolled back silently (D-081).
     *        Only edit() may pass true; anything added later that re-renders submitted
     *        blocks is safe without having to know this exists.
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     */
    private function shell(array $page, string $title, string $slug, array $blocks, ?array $sections = null, array $errors = [], ?string $notice = null, int $status = 200, bool $fromStorage = false): Response
    {
        $id = (int) $page['id'];

        return AdminView::render($this->container, __DIR__ . '/views', 'admin/builder', [
            'title' => t('pages.edit'),
            'nav' => 'pages',
            // Both: the picker shows the library's own cards (admin-media.css) inside its
            // own panel (admin-picker.css), and one definition of a card beats a short list.
            'styles' => ['admin-richtext.css', 'builder.css', 'builder-inspector.css', 'admin-media.css', 'admin-picker.css'],
            'scripts' => ['vendor/tiptap.bundle.min.js', 'richtext.js', 'media-picker.js', 'repeater.js'],
            'wide' => true,
            'bare' => true,
            'page' => $page,
            'titleValue' => $title,
            'slugValue' => $slug,
            'blocks' => $blocks,
            'sections' => PageEditorController::sectionMap($this->db(), $id, $sections),
            'errors' => $errors,
            'notice' => $notice,
            'fromStorage' => $fromStorage,
            // What this page was before the last few saves (D-088). Ids and times only:
            // drawing five lines does not need five whole pages of JSON.
            'revisions' => PageRevision::all($this->db(), $id),
            'zone' => \App\Support\Dates::zone($this->db()),
            'character' => Composition::active($this->db()),
            'registry' => $this->registry(),
            'canvasUrl' => Url::admin('pages', $id, 'canvas'),
            'insertUrl' => Url::admin('pages', $id, 'block'),
            // The other fragment endpoint: a whole band, for the choices a block cannot
            // show (D-099). Beside it rather than derived in the browser, so the one place
            // that knows this page's addresses goes on being this one.
            'bandUrl' => Url::admin('pages', $id, 'section'),
            'library' => $this->library(),
            // The shelves this site's blocks actually stand on, in the order D-104 declares
            // them — never every possible one, which would offer a filter that finds nothing.
            'libraryGroups' => array_values(array_filter(
                \App\Core\BlockDefinition::GROUPS,
                fn (string $group): bool => in_array($group, array_column($this->library(), 'group'), true),
            )),
            // What a media field offers. The editor asks for a picture by name, never by id.
            'pictures' => MediaReference::choices($this->db()),
            // The files a Downloads block may offer (D-127).
            'files' => \App\Modules\Media\MediaFiles::choices($this->db()),
            // What a form field offers: the forms of the page's own language (D-046).
            'formChoices' => \App\Modules\Forms\Form::choices($this->db(), (string) $page['locale']),
            // What a link field offers: this page's language, in tree order (D-034).
            'linkPages' => PageLinks::choices($this->db(), (string) $page['locale']),
            // Page settings live in the panel beside the canvas. Offering a parent is the
            // only place a cycle could be created, so the list already excludes this page
            // and everything under it (PageTree).
            'parents' => PageTree::parentOptions($this->db(), (string) $page['locale'], $id),
            'languages' => $this->languages($id, (string) $page['locale']),
            'translation' => $this->translation($id),
        ], $status);
    }

    /**
     * This page's standing against its source, with the source's language named for the
     * notices that say so.
     *
     * @return array{source: array<string, mixed>|null, stale: array<int, array{source: int, type: string, content: array<string, mixed>}>, missing: int, sourceLabel: string}
     */
    private function translation(int $id): array
    {
        $status = TranslationStatus::of($this->db(), $this->registry(), $id);
        $code = (string) ($status['source']['locale'] ?? '');
        $label = $code === '' ? '' : (string) ($this->db()->one('SELECT label FROM locales WHERE code = ?', [$code])['label'] ?? $code);

        return $status + ['sourceLabel' => $label];
    }

    /**
     * The site's languages as the builder's language menu offers them: this page's
     * version in each, or null where there is none yet (D-043).
     *
     * @return list<array{code: string, label: string, page: int|null, current: bool}>
     */
    private function languages(int $id, string $current): array
    {
        $versions = Translations::of($this->db(), $id);
        $languages = [];
        foreach ($this->container->get('locales') as $language) {
            $code = (string) $language['code'];
            $languages[] = [
                'code' => $code,
                'label' => (string) $language['label'],
                'page' => $versions[$code] ?? null,
                'current' => $code === $current,
            ];
        }

        return $languages;
    }

    /**
     * Every block that can be added, each with a picture of itself rendered from the
     * block and this site's design (BlockPreview). Missing files are generated here, the
     * same guard the compiled stylesheet uses.
     *
     * @return list<array{type: string, label: string, icon: string, group: string, summary: string, preview: string}>
     */
    private function library(): array
    {
        $cache = (string) $this->container->get('config')->get('app.cache_path');
        $stylesheet = Design::stylesheet($this->db(), $cache);
        $registry = $this->registry();

        $library = [];
        foreach ($registry->types() as $type) {
            $library[] = [
                'type' => $type,
                'label' => t('block.' . $type),
                // Declared in every block definition since the first one, validated at boot,
                // and until now drawn nowhere (D-084).
                'icon' => (string) $registry->get($type)['icon'],
                // Which shelf it sits on (D-104). Declared by the block, a closed set, so
                // the library's groups are the ones the blocks actually use.
                'group' => (string) $registry->get($type)['group'],
                // What the block is FOR. The picture shows its shape and the label names it;
                // neither says when to reach for it.
                'summary' => t('block.' . $type . '.summary'),
                'preview' => Url::asset('cache/previews/' . BlockPreview::file($registry, $type, $stylesheet, $cache)),
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
