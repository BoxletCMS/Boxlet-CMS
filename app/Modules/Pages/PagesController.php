<?php

namespace App\Modules\Pages;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Support\Dates;
use App\Support\Url;

/**
 * Admin: the page list, creating, publishing and deleting pages. Editing a page's
 * content is PageEditorController.
 */
final class PagesController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        // Narrowed by language and by words in the title or address (D-052), both in the
        // address so the view can be kept and shared. A search takes rows out of their tree,
        // so it hides the ordering controls; a language keeps whole sibling groups, so the
        // order can still be changed while one is chosen.
        $codes = array_column($this->container->get('locales'), 'code');
        $lang = is_string($request->query['lang'] ?? null) && in_array($request->query['lang'], $codes, true) ? $request->query['lang'] : '';
        $query = is_string($request->query['q'] ?? null) ? mb_substr(trim($request->query['q']), 0, 100) : '';
        $all = PageTree::listing($this->db());
        $shown = array_values(array_filter($all, static fn (array $page): bool => ($lang === '' || $page['locale'] === $lang)
            && ($query === '' || mb_stripos($page['title'], $query) !== false || mb_stripos('/' . $page['slug'], $query) !== false)));

        return AdminView::render($this->container, __DIR__ . '/views', 'admin/index', [
            'title' => t('pages.title'),
            'nav' => 'pages',
            // A list wants the room: a table in the reading column scrolled sideways (D-039).
            'wide' => true,
            'styles' => ['admin-pages.css'],
            // The drag is an addition: the Up and Down buttons work without either file,
            // and pages.js returns early when Sortable is not there.
            'scripts' => ['vendor/sortable.min.js', 'pages.js', 'admin-bulk.js'],
            'pages' => $shown,
            'total' => count($all),
            'lang' => $lang,
            'query' => $query,
            'codes' => $codes,
            'zone' => Dates::zone($this->db()),
            'localeLabels' => array_column($this->container->get('locales'), 'label', 'code'),
            // Translations with blocks behind their source, by page id (D-043, step 3).
            'stale' => TranslationStatus::counts($this->db(), $this->container->get('blocks')),
            // Where the page just placed was, for the one Undo the list offers (D-133).
            'undo' => $this->pullUndo(),
            'maxLevels' => PagePlacing::MAX_LEVELS,
        ]);
    }

    /**
     * Reordering, by drag or by the Up and Down buttons. One action for both, because
     * they are the same change: a sibling group gets a new order. The router checks the
     * CSRF token on every POST before this runs (Router::dispatch).
     *
     * @param array<string, string> $params
     */
    public function reorder(Request $request, string $locale, array $params): Response
    {
        $order = $request->input('order');
        $ids = [];
        foreach (explode(',', $order) as $id) {
            if (ctype_digit(trim($id))) {
                $ids[] = (int) $id;
            }
        }

        $done = $order !== ''
            ? PageTree::reorder($this->db(), $ids)
            : PageTree::move($this->db(), (int) $request->input('id'), $request->input('move'));

        if ($done) {
            Activity::record($this->db(), 'page', 'reordered', null, '');
        }
        $this->container->get('session')->set('flash', t($done ? 'pages.reordered' : 'pages.reorder_failed'));

        return Response::redirect(Url::admin('pages'));
    }

    /**
     * Placing a page under another, or out of one (D-133): → and ← in the list, the drag,
     * and Undo, which is the same request naming where the page was. The rules and the
     * translations that follow are PagePlacing's.
     *
     * @param array<string, string> $params
     */
    public function place(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $id = (int) $params['id'];
        $before = PagePlacing::whereIs($db, $id);
        // Back to the list as it was being looked at: one language, if one was chosen.
        $lang = $request->input('lang');
        $back = in_array($lang, array_column($this->container->get('locales'), 'code'), true)
            ? Url::admin('pages') . '?' . http_build_query(['lang' => $lang])
            : Url::admin('pages');
        $parent = $request->input('parent');
        $position = $request->input('position');
        $problem = match ($request->input('to')) {
            'in' => PagePlacing::indent($db, $id),
            'out' => PagePlacing::outdent($db, $id),
            default => PagePlacing::place($db, $id, ctype_digit($parent) ? (int) $parent : null, ctype_digit($position) ? (int) $position : null),
        };

        $session = $this->container->get('session');
        if ($problem !== null) {
            $session->set('flash', $problem);
            $session->set('flash_kind', 'error');

            return Response::redirect($back);
        }

        $page = Page::find($db, $id) ?? [];
        $title = (string) ($page['title'] ?? '');
        Activity::record($db, 'page', 'placed', $id, $title);
        $under = ($page['parent_id'] ?? null) === null ? null : Page::find($db, (int) $page['parent_id']);
        $session->set('flash', $under === null
            ? t('pages.place.done_top', ['title' => $title])
            : t('pages.place.done_under', ['title' => $title, 'parent' => (string) $under['title']]));
        // Undo, offered once on the list this lands on; an undo offers none of its own.
        if ($before !== null && $request->input('undo') !== '1') {
            $session->set('page_undo', ['id' => $id, 'parent' => $before['parent'], 'position' => $before['position']]);
        } else {
            $session->remove('page_undo');
        }

        return Response::redirect($back);
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, string $locale, array $params): Response
    {
        return $this->form([], []);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $title = trim($request->input('title'));
        $pageLocale = $request->input('locale');
        $home = $request->input('home') === '1';
        $slug = $home ? '' : trim($request->input('slug'));

        $errors = [];
        if ($title === '') {
            $errors['title'] = t('pages.title_required');
        }
        if (!in_array($pageLocale, array_column($this->container->get('locales'), 'code'), true)) {
            $errors['locale'] = t('pages.locale_invalid');
        }
        $template = null;
        foreach (Page::templates($db) as $candidate) {
            if ((string) $candidate['id'] === $request->input('template')) {
                $template = $candidate;
            }
        }
        if ($template === null && $request->input('template') !== '') {
            $errors['template'] = t('pages.template_invalid');
        }
        if ($errors === []) {
            if (!$home && $slug === '') {
                $slug = Slug::unique($db, $pageLocale, $title, null);
            }
            $problem = Slug::problem($db, $pageLocale, $slug, null);
            if ($problem !== null) {
                $errors[$home ? 'home' : 'slug'] = $problem;
            }
        }
        if ($errors !== []) {
            return $this->form($request->body, $errors, 422);
        }

        $registry = $this->container->get('blocks');
        $types = [];
        foreach ($template['blocks'] ?? [] as $type) {
            if ($registry->has($type)) {
                $types[] = $type;
            }
        }
        $id = Page::create($db, $registry, $pageLocale, $title, $slug, $template['id'] ?? null, $types);
        Activity::record($db, 'page', 'created', $id, $title);
        $this->container->get('session')->set('flash', t('pages.created'));

        return Response::redirect(Url::admin('pages', $id));
    }

    /**
     * @param array<string, string> $params
     */
    public function status(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $page = Page::find($this->db(), $id);
        if ($page === null) {
            return self::missing();
        }
        // Publish is the draft's when there is one (D-173): what the page list's button puts on
        // the site is what the editor shows. Unpublish keeps the page as the draft.
        $published = $request->input('status') === 'published';
        if ($published) {
            $errors = PagePublish::publish($this->container, $id);
            if ($errors !== []) {
                $this->container->get('session')->set('flash', t('pages.publish_refused', ['problem' => (string) reset($errors)]));

                return Response::redirect($request->input('return') === 'edit' ? Url::admin('pages', $id) : Url::admin('pages'));
            }
        } else {
            PagePublish::unpublish($this->container, $id);
            Activity::record($this->db(), 'page', 'unpublished', $id, (string) $page['title']);
        }
        $this->container->get('session')->set('flash', t($published ? 'pages.published' : 'pages.unpublished'));

        return Response::redirect($request->input('return') === 'edit' ? Url::admin('pages', $id) : Url::admin('pages'));
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $page = Page::find($this->db(), $id);
        if ($page === null) {
            return self::missing();
        }
        Page::delete($this->db(), $id);
        Activity::record($this->db(), 'page', 'deleted', $id, (string) $page['title']);
        Sitemap::refresh($this->container);
        $this->container->get('session')->set('flash', t('pages.deleted'));

        return Response::redirect(Url::admin('pages'));
    }

    public static function missing(): Response
    {
        return Response::admin(e(t('pages.not_found')), 404);
    }

    /**
     * @param array<mixed>          $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors, int $status = 200): Response
    {
        return AdminView::render($this->container, __DIR__ . '/views', 'admin/create', [
            'title' => t('pages.new'),
            'nav' => 'pages',
            'styles' => ['admin-pages.css'],
            'old' => $old,
            'errors' => $errors,
            'locales' => $this->container->get('locales'),
            'templates' => Page::templates($this->db()),
        ], $status);
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }

    /**
     * The Undo left by the last placing, taken so it is offered once.
     *
     * @return array{id: int, parent: int|null, position: int}|null
     */
    private function pullUndo(): ?array
    {
        $session = $this->container->get('session');
        $undo = $session->get('page_undo');
        $session->remove('page_undo');
        if (!is_array($undo) || !is_int($undo['id'] ?? null) || !is_int($undo['position'] ?? null)) {
            return null;
        }

        return ['id' => $undo['id'], 'parent' => is_int($undo['parent'] ?? null) ? $undo['parent'] : null, 'position' => $undo['position']];
    }
}
