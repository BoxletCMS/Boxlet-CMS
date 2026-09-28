<?php

namespace App\Modules\Menus;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Settings\SiteChrome;
use App\Support\Url;

/**
 * Admin: menus and their items (PLAN.md D-028).
 *
 * Ordering follows D-011 exactly, which is why one route takes both paths: a drag writes
 * the whole sibling order into a hidden field and submits the same form the Up and Down
 * buttons submit. One endpoint, one CSRF check — the router's — and nothing to keep in
 * step between a script and a button.
 */
final class MenusController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        return AdminView::render($this->container, __DIR__ . '/views', 'index', [
            'title' => t('menus.title'),
            'nav' => 'menus',
            'wide' => true,
            'styles' => ['admin-pages.css'],
            'menus' => Menu::all($this->db()),
            'locales' => $this->container->get('locales'),
            'errors' => [],
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $name = trim($request->input('name'));
        $wanted = trim($request->input('locale'));
        $codes = array_column($this->container->get('locales'), 'code');

        $errors = [];
        if ($name === '') {
            $errors['name'] = t('menus.name_required');
        } elseif (Menu::nameTaken($db, $wanted, $name)) {
            // The database enforces this too; saying it in words is what stops the owner
            // meeting a constraint violation.
            $errors['name'] = t('menus.name_taken');
        }
        if (!in_array($wanted, $codes, true)) {
            $errors['locale'] = t('menus.name_required');
        }

        if ($errors !== []) {
            return AdminView::render($this->container, __DIR__ . '/views', 'index', [
                'title' => t('menus.title'),
                'nav' => 'menus',
                'wide' => true,
                'styles' => ['admin-pages.css'],
                'menus' => Menu::all($db),
                'locales' => $this->container->get('locales'),
                'errors' => $errors,
            ], 422);
        }

        $id = Menu::create($db, $wanted, $name);
        Activity::record($db, 'menu', 'created', $id, $name);
        $this->flash(t('menus.created'));

        return Response::redirect(Url::admin('menus', $id));
    }

    /**
     * One menu: its items, and the form that adds another.
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $menu = Menu::find($db, (int) $params['id']);
        if ($menu === null) {
            return self::missing();
        }

        // ?edit=<item> opens that item's dialog on arrival, which is how Edit works without a
        // script (D-039). A number that is not one of this menu's items opens nothing.
        return $this->form($menu, [], 200, (int) ($request->query['edit'] ?? 0));
    }

    /**
     * @param array<string, string> $params
     */
    public function rename(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $id = (int) $params['id'];
        $menu = Menu::find($db, $id);
        if ($menu === null) {
            return self::missing();
        }

        $name = trim($request->input('name'));
        if ($name === '') {
            return $this->form($menu, ['name' => t('menus.name_required')], 422);
        }
        if (Menu::nameTaken($db, (string) $menu['locale'], $name, $id)) {
            return $this->form($menu, ['name' => t('menus.name_taken')], 422);
        }

        Menu::rename($db, $id, $name);
        // The header and footer choose their menu by name, so a rename they did not follow
        // would take the menu off the site without a word (found in the owner's review,
        // D-038). Followed here, where the rename happens.
        SiteChrome::followRename($db, (string) $menu['name'], $name);
        Activity::record($db, 'menu', 'renamed', $id, $name);
        $this->flash(t('menus.renamed'));

        return Response::redirect(Url::admin('menus', $id));
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $id = (int) $params['id'];
        $menu = Menu::find($db, $id);
        if ($menu === null) {
            return self::missing();
        }

        Menu::delete($db, $id);
        Activity::record($db, 'menu', 'deleted', $id, (string) $menu['name']);
        $this->flash(t('menus.deleted'));

        return Response::redirect(Url::admin('menus'));
    }

    /**
     * Adds an item. A page and an address are exclusive; the model keeps only one.
     *
     * @param array<string, string> $params
     */
    public function addItem(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $id = (int) $params['id'];
        $menu = Menu::find($db, $id);
        if ($menu === null) {
            return self::missing();
        }

        $pageId = (int) $request->input('page_id');
        $url = trim($request->input('url'));
        $parent = (int) $request->input('parent_id');

        if ($pageId <= 0 && $url === '') {
            return $this->form($menu, ['item' => t('menus.item.needs_target')], 422);
        }

        $item = Menu::addItem(
            $db,
            $id,
            $parent > 0 ? $parent : null,
            $pageId > 0 ? $pageId : null,
            $url === '' ? null : $url,
            $request->input('label'),
        );
        if ($item === null) {
            return $this->form($menu, ['item' => t('menus.item.parent_invalid')], 422);
        }

        // An address the model refused is stored as null, so the item exists and points
        // nowhere. Saying so beats a silent save the owner discovers on the site.
        $stored = Menu::findItem($db, $item);
        $refused = $url !== '' && $pageId <= 0 && ($stored['url'] ?? null) === null;
        // The item is kept rather than thrown away — the owner typed a label for it and it
        // shows in the list, marked, exactly as one whose page was later deleted does. But
        // it is a refusal, so it is not coloured as a success.
        $this->edited($id);
        $this->flash($refused ? t('menus.item.url_refused') : t('menus.item.added'), $refused ? 'warning' : 'success');

        return Response::redirect(Url::admin('menus', $id));
    }

    /**
     * Changes an item's destination and words (D-038). Its place in the menu is changed by
     * dragging or the arrows, never here, so an edit cannot move it by accident.
     *
     * @param array<string, string> $params
     */
    public function updateItem(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $item = Menu::findItem($db, (int) $params['item']);
        if ($item === null || (int) $item['menu_id'] !== (int) $params['id']) {
            return self::missing();
        }

        $pageId = (int) $request->input('page_id');
        $url = trim($request->input('url'));
        if ($pageId <= 0 && $url === '') {
            $menu = Menu::find($db, (int) $item['menu_id']);

            return $menu === null ? self::missing() : $this->form($menu, ['item' => t('menus.item.needs_target')], 422);
        }

        Menu::updateItem($db, (int) $item['id'], $pageId > 0 ? $pageId : null, $url === '' ? null : $url, $request->input('label'));
        $stored = Menu::findItem($db, (int) $item['id']);
        $refused = $url !== '' && $pageId <= 0 && ($stored['url'] ?? null) === null;
        $this->edited((int) $item['menu_id']);
        $this->flash($refused ? t('menus.item.url_refused') : t('menus.item.saved'), $refused ? 'warning' : 'success');

        return Response::redirect(Url::admin('menus', (int) $item['menu_id']));
    }

    /**
     * @param array<string, string> $params
     */
    public function deleteItem(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $item = Menu::findItem($db, (int) $params['item']);
        if ($item === null) {
            return self::missing();
        }

        Menu::deleteItem($db, (int) $item['id']);
        $this->edited((int) $item['menu_id']);
        $this->flash(t('menus.item.deleted'));

        return Response::redirect(Url::admin('menus', (int) $item['menu_id']));
    }

    /**
     * Both ordering paths, D-011: a posted whole order, or one item moved by a button.
     *
     * @param array<string, string> $params
     */
    public function order(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $id = (int) $params['id'];
        if (Menu::find($db, $id) === null) {
            return self::missing();
        }

        $order = $request->input('order');
        $ids = [];
        foreach (explode(',', $order) as $value) {
            if (ctype_digit(trim($value))) {
                $ids[] = (int) trim($value);
            }
        }

        $done = $order !== ''
            ? MenuTree::reorder($db, $ids)
            : MenuTree::move($db, (int) $request->input('item'), $request->input('move'));

        if ($done) {
            $this->edited($id);
        }
        $this->flash(t($done ? 'menus.reordered' : 'menus.reorder_failed'));

        return Response::redirect(Url::admin('menus', $id));
    }

    public static function missing(): Response
    {
        return Response::admin(e(t('menus.not_found')), 404);
    }

    /**
     * @param array<string, mixed> $menu
     * @param array<string, string> $errors
     */
    private function form(array $menu, array $errors, int $status = 200, int $editing = 0): Response
    {
        $db = $this->db();
        $id = (int) $menu['id'];

        return AdminView::render($this->container, __DIR__ . '/views', 'edit', [
            'title' => (string) $menu['name'],
            'nav' => 'menus',
            'wide' => true,
            'styles' => ['admin-pages.css'],
            'scripts' => ['vendor/sortable.min.js', 'menus.js'],
            'menu' => $menu,
            'items' => MenuTree::admin($db, $id),
            'pages' => Menu::pageChoices($db, (string) $menu['locale']),
            'editing' => $editing,
            'errors' => $errors,
        ], $status);
    }

    /** A change to a menu's items, logged against the menu (D-052). */
    private function edited(int $menuId): void
    {
        $name = (string) (Menu::find($this->db(), $menuId)['name'] ?? '');
        Activity::record($this->db(), 'menu', 'edited', $menuId, $name);
    }

    private function flash(string $message, string $kind = 'success'): void
    {
        $session = $this->container->get('session');
        $session->set('flash', $message);
        $session->set('flash_kind', $kind);
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
