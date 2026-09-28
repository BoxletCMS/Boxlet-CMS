<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Design\Composition;
use App\Modules\Media\MediaReference;
use App\Support\Url;

/**
 * The page editor: one form holding every block's fields, submitted as one POST.
 *
 * JavaScript only adds, removes and reorders field groups. Without it the same buttons
 * submit, and the server re-renders the form with the change applied but nothing
 * saved. Only Save writes to the database.
 */
final class PageEditorController
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

        return $this->form($page, (string) $page['title'], (string) $page['slug'], $this->storedBlocks((int) $page['id']));
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $page = Page::find($db, (int) $params['id']);
        if ($page === null) {
            return PagesController::missing();
        }
        $id = (int) $page['id'];

        // PHP drops input past max_input_vars without any error. Saving what arrived would
        // silently delete content, so a form that did not arrive whole is refused.
        if (self::truncated($request)) {
            $message = t('pages.editor.truncated', ['limit' => (int) ini_get('max_input_vars')]);

            return $this->reject($request, $page, (string) $page['title'], (string) $page['slug'], $this->storedBlocks($id), null, [], $message);
        }

        $registry = $this->registry();
        // The blocks as stored, by id: the type an existing block keeps whatever the form
        // claims, and the content a block that sent only its skeleton is restored from
        // (D-081).
        $stored = [];
        foreach ($this->storedBlocks($id) as $block) {
            if ($block['id'] !== null) {
                $stored[$block['id']] = $block;
            }
        }
        $parsed = BlockForm::parse($registry, $request->body['blocks'] ?? [], $stored);
        $blocks = $parsed['blocks'];
        /* AND THE SECTIONS THE FORM SENT (D-098). A body with none is not an error and is
           not a page of no sections: it is a caller that has nothing to say about the
           arrangement — an older form, a hand-made request — and Page::update() answers it
           with one section per block, leaving every arrangement as it was. */
        $sections = isset($request->body['sections']) && is_array($request->body['sections'])
            ? SectionForm::parse($request->body['sections'], self::storedSections($db, $id))
            : null;
        $title = trim($request->input('title'));
        $slug = trim($request->input('slug'));
        $action = $request->input('action');

        if ($action === 'add' && $registry->has($request->input('add_type'))) {
            $type = $request->input('add_type');
            $character = Composition::active($db);
            $blocks[] = [
                // A block that does not exist yet is named for this render only (D-094).
                'key' => BlockForm::key(null, count($blocks)),
                'id' => null,
                'type' => $type,
                'content' => $registry->fresh($type),
                'style' => Composition::style($character, $type),
                'layout' => Composition::layout($registry, $character, $type),
                // AND A SECTION OF ITS OWN, named for this render too. A stored block's
                // section is `s{id}`, so `m{n}` cannot collide with one — and two blocks
                // added before a save must not both answer to m0, which would stand them
                // side by side in one band nobody asked for.
                'section' => SectionForm::key(null, count($blocks)),
                'column' => 0,
            ];

            return $this->form($page, $title, $slug, $blocks, $sections);
        }
        // These three name a BLOCK, not a position (D-094): a form rendered before
        // something moved would otherwise act on whatever has taken that slot since.
        if (preg_match('~^(up|down)-([bn][0-9]{1,9})$~', $action, $move)) {
            return $this->form($page, $title, $slug, BlockForm::move($blocks, $move[2], $move[1]), $sections);
        }
        // A repeater's own controls, for a browser with no JavaScript (PLAN.md O-11). The
        // field name is matched against what a field name may be, and then against what
        // the block actually declares, inside BlockForm — a posted name is not a key.
        if (preg_match('~^item-(up|down)-([bn][0-9]{1,9})-([a-z][a-z0-9_]*)-(\d+)$~', $action, $move)) {
            return $this->again($request, $page, $title, $slug, BlockForm::moveItem($registry, $blocks, $move[2], $move[3], (int) $move[4], $move[1]), $sections);
        }
        if (preg_match('~^item-add-([bn][0-9]{1,9})-([a-z][a-z0-9_]*)$~', $action, $add)) {
            return $this->again($request, $page, $title, $slug, BlockForm::addItem($registry, $blocks, $add[1], $add[2]), $sections);
        }
        /*
         * BACK TO WHAT THIS PAGE WAS (PLAN.md D-088).
         *
         * Whatever is on screen is deliberately DISCARDED: the person pressed "restore",
         * and restoring to a revision while keeping the edits that are open would be
         * neither one page nor the other.
         *
         * It records the current page first, so a restore can itself be undone — pressing
         * it by mistake must not be the one action in this editor with no way back.
         *
         * Then it is an ordinary save, through Page::update() like any other: the same
         * media resolution, the same sitemap refresh, the same activity line. A restore
         * with a path of its own would be the one path nobody exercises until it matters.
         */
        if (preg_match('~^restore-(\d+)$~', $action, $restore)) {
            $revision = PageRevision::find($db, $registry, $id, (int) $restore[1]);
            if ($revision === null) {
                return $this->reject($request, $page, $title, $slug, $blocks, $sections, [], t('pages.restore_gone'));
            }
            PageRevision::record($db, $registry, $id);
            Page::update($db, $registry, $id, [
                'title' => $revision['title'],
                'slug' => $revision['slug'],
                'parent_id' => $revision['parent_id'],
                'status' => $revision['status'],
                'seo_json' => $revision['seo_json'],
            ], $revision['blocks'], $revision['sections']);
            Activity::record($db, 'page', 'saved', $id, $revision['title']);
            Sitemap::refresh($this->container);
            $this->container->get('session')->set('flash', t('pages.restored'));

            return Response::redirect(Url::admin('pages', $id));
        }

        // The plain editor sends no settings fields, so each falls back to what the page
        // already has. Only the visual editor's page panel submits them.
        $settings = self::settings($request, $db, $page, $id);
        $errors = $parsed['errors'];
        if ($settings['parent_id'] === false) {
            $errors['parent'] = t('pages.parent_invalid');
            $settings['parent_id'] = $page['parent_id'] === null ? null : (int) $page['parent_id'];
        }
        if ($title === '') {
            $errors['title'] = t('pages.title_required');
        }
        $slugProblem = Slug::problem($db, (string) $page['locale'], $slug, $id);
        if ($slugProblem !== null) {
            $errors['slug'] = $slugProblem;
        }
        if ($errors !== []) {
            // What was submitted, so a rejected save shows the settings the user chose
            // rather than the ones still stored.
            return $this->reject($request, $settings + $page, $title, $slug, $blocks, $sections, $errors, t('pages.editor.errors'));
        }

        // What the page was, before it stops being that (D-088). Recorded here rather than
        // inside Page::update() because a revision is an editing event: the demo seed calls
        // update() too, and a fresh install does not want history nobody made.
        // Said when it happens, so the owner knows a link out there did not just break
        // (D-129): the same test Redirects::slugChanged() keeps the old slug by. The old
        // address is read before the save, while the page is still there.
        $kept = (string) $page['slug'] !== $slug && (string) $page['slug'] !== '' && $page['published_at'] !== null;
        $oldAddress = Url::page((string) $page['locale'], (string) $page['slug']);
        PageRevision::record($db, $registry, $id);
        Page::update($db, $registry, $id, ['title' => $title, 'slug' => $slug] + $settings, $blocks, $sections);
        Activity::record($db, 'page', 'saved', $id, $title);
        Sitemap::refresh($this->container);
        $this->container->get('session')->set('flash', $kept ? t('pages.saved_old_address', ['old' => $oldAddress]) : t('pages.saved'));

        return Response::redirect(Url::admin('pages', $id));
    }

    /**
     * True when the form did not arrive whole: its last field, _end, is missing, or the
     * number of fields reached max_input_vars.
     */
    private static function truncated(Request $request): bool
    {
        if (($request->body['_end'] ?? null) !== '1') {
            return true;
        }
        $limit = (int) ini_get('max_input_vars');

        return $limit > 0 && self::countFields($request->body) >= $limit;
    }

    /**
     * @param array<mixed> $values
     */
    private static function countFields(array $values): int
    {
        $count = 0;
        foreach ($values as $value) {
            $count += is_array($value) ? self::countFields($value) : 1;
        }

        return $count;
    }

    /**
     * The page's settings as submitted, falling back to what is stored for any the form
     * did not send.
     *
     * parent_id comes back as false when a parent was named that this page may not have.
     * The list of allowed parents already excludes the page and its descendants, so this
     * is what stops a crafted request creating a cycle the interface would not offer.
     *
     * @param array<string, mixed> $page
     * @return array{parent_id: int|null|false, status: string, seo_json: string}
     */
    private static function settings(Request $request, Db $db, array $page, int $id): array
    {
        $stored = $page['parent_id'] === null ? null : (int) $page['parent_id'];
        $status = $request->input('status');
        $settings = [
            'parent_id' => $stored,
            'status' => in_array($status, ['draft', 'published'], true) ? $status : (string) $page['status'],
            'seo_json' => self::seo($request, $page),
        ];

        if (!array_key_exists('parent_id', $request->body)) {
            return $settings;
        }
        $submitted = $request->input('parent_id');
        if ($submitted === '') {
            $settings['parent_id'] = null;

            return $settings;
        }

        $allowed = array_column(PageTree::parentOptions($db, (string) $page['locale'], $id), 'id');
        $settings['parent_id'] = in_array((int) $submitted, $allowed, true) ? (int) $submitted : false;

        return $settings;
    }

    /**
     * The page's meta title and description as submitted, already encoded for storage
     * (D-004).
     *
     * Presence decides, the same rule parent_id follows: a field the form did not send
     * keeps what is stored, a field sent empty was cleared on purpose. Without that
     * distinction any save from a form lacking these two — which is every save made
     * before this existed, and any made by a form added later — would quietly erase
     * what the owner wrote.
     *
     * @param array<string, mixed> $page
     */
    private static function seo(Request $request, array $page): string
    {
        $seo = Page::seo($page);
        foreach (['title', 'description'] as $field) {
            if (array_key_exists('seo_' . $field, $request->body)) {
                $seo[$field] = trim($request->input('seo_' . $field));
            }
        }

        return Page::seoJson($seo);
    }

    /**
     * The editor the request came from, re-rendered with the change applied and nothing
     * saved: a repeater's Add, Move up or Move down pressed without JavaScript.
     *
     * A BLOCK's own move and remove render the plain form unconditionally, and that is
     * safe only because builder-inspector.css hides them inside the visual editor's panel
     * — they would, in its own words, "either do nothing or throw the user back into the
     * plain editor". A repeater's controls are NOT hidden there, because in the panel they
     * do real work, so a submit from the panel has to come back as the panel. Without
     * this, one press of Add on an item would replace the canvas with the plain form and
     * take every unsaved change on the page with it.
     *
     * Not reject(): that answers 422 for a save that failed. Nothing here failed, so this
     * answers 200.
     *
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     */
    private function again(Request $request, array $page, string $title, string $slug, array $blocks, ?array $sections = null): Response
    {
        if ($request->input('editor') === 'builder') {
            return (new PageBuilderController($this->container))->again($page, $title, $slug, $blocks, $sections);
        }

        return $this->form($page, $title, $slug, $blocks, $sections);
    }

    /**
     * A save that did not validate re-renders the editor it was sent from, so nobody is
     * moved to a different screen at the moment they have to fix something. Everything
     * before this point — parsing, validation, storage — is the same for both.
     *
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @param array<string, string> $errors
     */
    private function reject(Request $request, array $page, string $title, string $slug, array $blocks, ?array $sections, array $errors, ?string $notice): Response
    {
        if ($request->input('editor') === 'builder') {
            return (new PageBuilderController($this->container))->rejected($page, $title, $slug, $blocks, $sections, $errors, $notice);
        }

        return $this->form($page, $title, $slug, $blocks, $sections, $errors, $notice, 422);
    }

    /**
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}>
     */
    private function storedBlocks(int $pageId): array
    {
        return Page::editable($this->db(), $this->registry(), $pageId);
    }

    /**
     * This page's section ids, for SectionForm::parse to tell a key of this page's from one
     * that arrived from somewhere else — the rule BlockForm::parse follows for a block id.
     *
     * @return array<int, int>
     */
    private static function storedSections(Db $db, int $pageId): array
    {
        $ids = [];
        foreach (Page::editableSections($db, $pageId) as $section) {
            if ($section['id'] !== null) {
                $ids[$section['id']] = $section['id'];
            }
        }

        return $ids;
    }

    /**
     * The sections the views read, by key. Built from what is in hand — the list the form
     * sent, or the one that is stored — so a rejected save redraws the arrangement the
     * author chose and not the one they are trying to change.
     *
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @return array<string, array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>
     */
    public static function sectionMap(Db $db, int $pageId, ?array $sections): array
    {
        $list = $sections ?? Page::editableSections($db, $pageId);
        $map = [];
        foreach ($list as $section) {
            $map[$section['key']] = $section;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @param array<string, string> $errors
     */
    private function form(array $page, string $title, string $slug, array $blocks, ?array $sections = null, array $errors = [], ?string $notice = null, int $status = 200): Response
    {
        return AdminView::render($this->container, __DIR__ . '/views', 'admin/edit', [
            'title' => t('pages.edit'),
            'nav' => 'pages',
            // Both: the picker shows the library's own cards (admin-media.css) inside its
            // own panel (admin-picker.css), and one definition of a card beats a short list.
            'styles' => ['admin-richtext.css', 'admin-pages.css', 'admin-media.css', 'admin-picker.css'],
            'scripts' => ['vendor/tiptap.bundle.min.js', 'richtext.js', 'media-picker.js', 'repeater.js'],
            'character' => Composition::active($this->db()),
            'page' => $page,
            'titleValue' => $title,
            'slugValue' => $slug,
            // update() is the save route for both editors and reads parent_id from the
            // request, so an editor that does not render the control submits nothing and
            // the page is un-parented on every save.
            'parents' => PageTree::parentOptions($this->db(), (string) $page['locale'], isset($page['id']) ? (int) $page['id'] : null),
            'blocks' => $blocks,
            'sections' => self::sectionMap($this->db(), isset($page['id']) ? (int) $page['id'] : 0, $sections),
            'errors' => $errors,
            'notice' => $notice,
            'registry' => $this->registry(),
            // What a media field offers. The editor asks for a picture by name, never by id.
            'pictures' => MediaReference::choices($this->db()),
            // The files a Downloads block may offer (D-127).
            'files' => \App\Modules\Media\MediaFiles::choices($this->db()),
            // What a form field offers: the forms of the page's own language (D-046).
            'formChoices' => \App\Modules\Forms\Form::choices($this->db(), (string) $page['locale']),
            // What a link field offers: this page's language, in tree order (D-034).
            'linkPages' => PageLinks::choices($this->db(), (string) $page['locale']),
        ], $status);
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
