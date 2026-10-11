<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
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
 *
 * ON THE DRAFT (PLAN.md D-173). Both editors show the page's draft when it has one, and Save
 * writes the draft: nothing a visitor sees changes until Publish, which validates the draft
 * and writes it into the published page (PagePublish). A save names the draft version its
 * form was made from, so a second tab cannot quietly overwrite the first.
 *
 * @phpstan-import-type Document from PageDocument
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
        $current = $page === null ? null : PageDraft::current($this->db(), $this->registry(), (int) $page['id']);
        if ($page === null || $current === null) {
            return PagesController::missing();
        }
        $document = $current['document'];

        return $this->form(self::shown($page, $document), $document['title'], $document['slug'], $document['blocks'], $document['sections'], $current['version']);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $page = Page::find($db, (int) $params['id']);
        $current = $page === null ? null : PageDraft::current($db, $this->registry(), (int) $page['id']);
        if ($page === null || $current === null) {
            return PagesController::missing();
        }
        $id = (int) $page['id'];
        $document = $current['document'];
        // The draft version this form was made from; a later one means another tab saved.
        $from = ctype_digit($request->input('draft_version')) ? (int) $request->input('draft_version') : $current['version'];

        // PHP drops input past max_input_vars without any error. Saving what arrived would
        // silently delete content, so a form that did not arrive whole is refused.
        if (PageSubmission::truncated($request)) {
            $message = t('pages.editor.truncated', ['limit' => (int) ini_get('max_input_vars')]);

            return $this->reject($request, self::shown($page, $document), $document['title'], $document['slug'], $document['blocks'], $document['sections'], $from, [], $message);
        }

        $registry = $this->registry();
        // The blocks as the form was made from them, by id: the type an existing block keeps
        // whatever the form claims — the draft's, when there is one, since that is what was
        // shown.
        $stored = [];
        foreach ($document['blocks'] as $block) {
            if ($block['id'] !== null) {
                $stored[$block['id']] = $block;
            }
        }
        $parsed = BlockForm::parse($registry, $request->body['blocks'] ?? [], $stored);
        $blocks = $parsed['blocks'];
        /* AND THE SECTIONS THE FORM SENT (D-098). A body with none is not an error and is
           not a page of no sections: it is a caller that has nothing to say about the
           arrangement — an older form, a hand-made request — and the bands it was made from
           are kept (PageSubmission::arranged). */
        $sections = isset($request->body['sections']) && is_array($request->body['sections'])
            ? SectionForm::parse($request->body['sections'], PageSubmission::sectionIds($document))
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
                'style' => \App\Modules\Design\SectionStyle::normalize([]), // every key '' — the character's (D-165)
                'layout' => Composition::layout($registry, $character, $type),
                // AND A SECTION OF ITS OWN, named for this render too. A stored block's
                // section is `s{id}`, so `m{n}` cannot collide with one — and two blocks
                // added before a save must not both answer to m0, which would stand them
                // side by side in one band nobody asked for.
                'section' => SectionForm::key(null, count($blocks)),
                'column' => 0,
            ];

            return $this->form($page, $title, $slug, $blocks, $sections, $from);
        }
        // These three name a BLOCK, not a position (D-094): a form rendered before
        // something moved would otherwise act on whatever has taken that slot since.
        if (preg_match('~^(up|down)-([bn][0-9]{1,9})$~', $action, $move)) {
            return $this->form($page, $title, $slug, BlockForm::move($blocks, $move[2], $move[1]), $sections, $from);
        }
        // A repeater's own controls, for a browser with no JavaScript (PLAN.md O-11). The
        // field name is matched against what a field name may be, and then against what
        // the block actually declares, inside BlockForm — a posted name is not a key.
        if (preg_match('~^item-(up|down)-([bn][0-9]{1,9})-([a-z][a-z0-9_]*)-(\d+)$~', $action, $move)) {
            return $this->again($request, $page, $title, $slug, BlockForm::moveItem($registry, $blocks, $move[2], $move[3], (int) $move[4], $move[1]), $sections, $from);
        }
        // One of the owner's patterns let go; the page on screen is redrawn as it was sent.
        if (preg_match('~^pattern-delete-(\d{1,9})$~', $action, $gone)) {
            PagePattern::delete($db, (int) $gone[1]);

            return $this->form($page, $title, $slug, $blocks, $sections, $from);
        }
        if (preg_match('~^item-add-([bn][0-9]{1,9})-([a-z][a-z0-9_]*)$~', $action, $add)) {
            return $this->again($request, $page, $title, $slug, BlockForm::addItem($registry, $blocks, $add[1], $add[2]), $sections, $from);
        }
        /*
         * BACK TO WHAT THIS PAGE WAS (PLAN.md D-088), INTO THE DRAFT (D-173). Whatever is on
         * screen is deliberately DISCARDED: the person pressed "restore", and restoring while
         * keeping the edits that are open would be neither one page nor the other. Nothing on
         * the site changes until Publish, which is what makes a restore safe to press.
         */
        if (preg_match('~^restore-(\d+)$~', $action, $restore)) {
            if (!PagePublish::restore($this->container, $id, (int) $restore[1], $from)) {
                $gone = PageRevision::find($db, $registry, $id, (int) $restore[1]) === null;

                return $this->reject($request, $page, $title, $slug, $blocks, $sections, $from, [], t($gone ? 'pages.restore_gone' : 'pages.draft.conflict'));
            }
            $this->container->get('session')->set('flash', t('pages.restored'));

            return Response::redirect(Url::admin('pages', $id));
        }
        // Back to the published page: the draft goes (D-173). Offered only where there is one.
        if ($action === 'discard') {
            $this->container->get('session')->set('flash', t(PagePublish::discard($this->container, $id) ? 'pages.draft.discarded' : 'pages.draft.nothing'));

            return Response::redirect(Url::admin('pages', $id));
        }

        // The plain editor sends no settings fields, so each falls back to the document's.
        // Only the visual editor's page panel submits them.
        $settings = PageSubmission::settings($request, $db, $page, $document);
        $errors = $parsed['errors'];
        if ($settings['parent_id'] === false) {
            $errors['parent'] = t('pages.parent_invalid');
            $settings['parent_id'] = $document['parent_id'];
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
            return $this->reject($request, ['parent_id' => $settings['parent_id'], 'seo_json' => $settings['seo_json']] + $page, $title, $slug, $blocks, $sections, $from, $errors, t('pages.editor.errors'));
        }

        $arranged = PageSubmission::arranged($blocks, $sections, $document);
        $draft = PageDocument::read($registry, [
            'title' => $title,
            'slug' => $slug,
            'parent_id' => $settings['parent_id'],
            'status' => (string) $page['status'],
            'seo_json' => $settings['seo_json'],
            'blocks' => $arranged['blocks'],
            'sections' => $arranged['sections'],
        ]);
        // A PATTERN, put in or kept (D-173), with the draft as it stands on screen: inserted at
        // the end of the page, or the chosen section saved under the name given.
        $pattern = null;
        if ($draft !== null && $action === 'pattern-insert') {
            $pattern = PagePattern::find($db, $registry, $request->input('pattern'), Composition::active($db), (string) $page['locale']);
            if ($pattern === null) {
                return $this->reject($request, $page, $title, $slug, $blocks, $sections, $from, [], t('patterns.gone'));
            }
            $draft = PagePattern::insert($draft, $pattern);
        }
        if ($draft !== null && $action === 'pattern-save') {
            $pattern = PagePattern::fromSection($draft, $request->input('pattern_section'));
            if ($pattern === null || trim($request->input('pattern_name')) === '') {
                return $this->reject($request, $page, $title, $slug, $blocks, $sections, $from, ['pattern' => t('patterns.save_needs')], t('pages.editor.errors'));
            }
            PagePattern::save($db, $request->input('pattern_name'), $pattern);
        }
        $version = $draft === null ? null : PageDraft::save($db, $id, $draft, $from, PagePublish::admin($this->container));
        if ($version === null) {
            return $this->reject($request, $page, $title, $slug, $blocks, $sections, $from, [], t('pages.draft.conflict'));
        }
        if ($action !== 'publish') {
            $said = ['pattern-insert' => 'patterns.inserted', 'pattern-save' => 'patterns.saved'][$action] ?? 'pages.draft.saved';
            $this->container->get('session')->set('flash', t($said));

            return Response::redirect(Url::admin('pages', $id));
        }

        // Said when it happens, so the owner knows a link out there did not just break
        // (D-129): the same test Redirects::slugChanged() keeps the old slug by. The old
        // address is read before the publish, while the page is still there.
        $kept = (string) $page['slug'] !== $slug && (string) $page['slug'] !== '' && $page['published_at'] !== null;
        $oldAddress = Url::page((string) $page['locale'], (string) $page['slug']);
        $problems = PagePublish::publish($this->container, $id);
        if ($problems !== []) {
            return $this->reject($request, $page, $title, $slug, $blocks, $sections, $version, $problems, t('pages.editor.errors'));
        }
        $this->container->get('session')->set('flash', $kept ? t('pages.saved_old_address', ['old' => $oldAddress]) : t('pages.published'));

        return Response::redirect(Url::admin('pages', $id));
    }

    /**
     * The pages row as an editor shows it: its settings from the document it is editing, so a
     * draft's title, address, parent and SEO are what the fields hold. Its state stays the
     * row's — whether the page is on the site is not the draft's to say.
     *
     * @param array<string, mixed> $page
     * @param Document $document
     * @return array<string, mixed>
     */
    private static function shown(array $page, array $document): array
    {
        return ['title' => $document['title'], 'slug' => $document['slug'], 'parent_id' => $document['parent_id'], 'seo_json' => $document['seo_json']] + $page;
    }

    /**
     * The form re-rendered with the change applied and nothing saved: a repeater's Add, Move
     * up or Move down pressed without JavaScript. Only the plain editor posts here since the
     * builder keeps its document in the browser (D-175).
     *
     * Not reject(): that answers 422 for a save that failed. Nothing here failed, so this
     * answers 200.
     *
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @param int $version the draft version the form was made from, carried to the next save
     */
    private function again(Request $request, array $page, string $title, string $slug, array $blocks, ?array $sections, int $version): Response
    {
        return $this->form($page, $title, $slug, $blocks, $sections, $version);
    }

    /**
     * A save that did not validate re-renders the editor it was sent from, so nobody is
     * moved to a different screen at the moment they have to fix something. Everything
     * before this point — parsing, validation, storage — is the same for both.
     *
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @param array<string, string> $errors
     */
    private function reject(Request $request, array $page, string $title, string $slug, array $blocks, ?array $sections, int $version, array $errors, ?string $notice): Response
    {
        return $this->form($page, $title, $slug, $blocks, $sections, $version, $errors, $notice, 422);
    }

    /**
     * The sections the views read, by key. Built from what is in hand — the list the form
     * sent, or else the document's (the draft's, or the stored page's) — so a rejected save
     * redraws the arrangement the author chose and not the one they are trying to change.
     *
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @return array<string, array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>
     */
    public static function sectionMap(Db $db, Blocks $registry, int $pageId, ?array $sections): array
    {
        $list = $sections ?? (PageDraft::current($db, $registry, $pageId)['document']['sections'] ?? []);
        $map = [];
        foreach ($list as $section) {
            $map[$section['key']] = $section;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $page
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string, section?: string, column?: int}> $blocks
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections
     * @param int $version the draft version the form is made from (0: there is no draft)
     * @param array<string, string> $errors
     */
    private function form(array $page, string $title, string $slug, array $blocks, ?array $sections, int $version, array $errors = [], ?string $notice = null, int $status = 200): Response
    {
        return AdminView::render($this->container, __DIR__ . '/views', 'admin/edit', [
            'title' => t('pages.edit'),
            'nav' => 'pages',
            // Both: the picker shows the library's own cards (admin-media.css) inside its
            // own panel (admin-picker.css), and one definition of a card beats a short list.
            'styles' => ['admin-richtext.css', 'admin-richtext-tags.css', 'admin-pages.css', 'admin-patterns.css', 'admin-media.css', 'admin-picker.css', 'admin-browser.css', 'vendor/cropper.min.css', 'admin-crop.css'],
            'scripts' => ['vendor/tiptap.bundle.min.js', 'richtext-link.js', 'richtext.js', 'vendor/cropper.min.js', 'media-browser-upload.js', 'media-browser.js', 'media-picker.js', 'repeater.js'],
            'character' => Composition::active($this->db()),
            'page' => $page,
            'titleValue' => $title,
            'slugValue' => $slug,
            // update() is the save route for both editors and reads parent_id from the
            // request, so an editor that does not render the control submits nothing and
            // the page is un-parented on every save.
            'parents' => PageTree::parents($this->db(), (string) $page['locale'], isset($page['id']) ? (int) $page['id'] : null),
            'blocks' => $blocks,
            'sections' => self::sectionMap($this->db(), $this->registry(), (int) $page['id'], $sections),
            // Which draft the form was made from, and what the page is: published, published
            // with changes not yet published, or a draft (D-173).
            'draftVersion' => $version,
            'state' => PageDraft::state($this->db(), $page),
            // The owner's patterns and the design set's, to put into the page (D-173).
            'patterns' => [
                'mine' => PagePattern::mine($this->db()),
                'set' => PagePattern::fromSet(Composition::active($this->db()), (string) $page['locale']),
            ],
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
