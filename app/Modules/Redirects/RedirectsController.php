<?php

namespace App\Modules\Redirects;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Support\Url;

/**
 * Admin: the addresses that lead elsewhere (PLAN.md D-129). The owner's rules for an old
 * site's addresses, made and removed here, and the old slugs Boxlet kept on its own, which
 * can only be removed: they are made by renaming a page.
 */
final class RedirectsController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        return $this->screen([], ['from' => '', 'page' => '', 'url' => '']);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $typed = ['from' => trim($request->input('from')), 'page' => trim($request->input('page')), 'url' => trim($request->input('url'))];
        $from = RedirectList::fromTyped($typed['from']);
        $pageId = ctype_digit($typed['page']) ? (int) $typed['page'] : null;

        $errors = [];
        $problem = RedirectList::fromProblem($db, $from);
        if ($problem !== null) {
            $errors['from'] = $problem;
        }
        if ($pageId !== null && $typed['url'] !== '') {
            $errors['url'] = t('redirects.to_both');
        } elseif ($pageId === null && $typed['url'] === '') {
            $errors['page'] = t('redirects.to_required');
        } elseif ($pageId !== null && $db->one('SELECT id FROM pages WHERE id = ?', [$pageId]) === null) {
            $errors['page'] = t('redirects.to_required');
        } elseif ($pageId === null && !RedirectList::urlAllowed($typed['url'])) {
            $errors['url'] = t('redirects.url_invalid');
        }

        if ($errors !== []) {
            return $this->screen($errors, $typed, 422);
        }

        $id = RedirectList::addRule($db, $from, $pageId, $pageId === null ? $typed['url'] : null);
        Activity::record($db, 'redirect', 'created', $id, $from);
        $this->flash(t('redirects.created', ['from' => $from]));

        return Response::redirect(Url::admin('redirects'));
    }

    /**
     * Removes a rule or a kept old address. Removing a kept one is the owner's choice to let
     * that address answer 404, which is theirs to make.
     *
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $row = $db->one('SELECT * FROM redirects WHERE id = ?', [(int) $params['id']]);
        if ($row === null) {
            return Response::redirect(Url::admin('redirects'));
        }
        $db->query('DELETE FROM redirects WHERE id = ?', [(int) $row['id']]);
        $shown = RedirectList::shownPath($row);
        Activity::record($db, 'redirect', 'deleted', (int) $row['id'], $shown);
        $this->flash(t('redirects.deleted', ['from' => $shown]));

        return Response::redirect(Url::admin('redirects'));
    }

    /**
     * @param array<string, string> $errors
     * @param array{from: string, page: string, url: string} $typed
     */
    private function screen(array $errors, array $typed, int $status = 200): Response
    {
        $db = $this->db();

        return AdminView::render($this->container, __DIR__ . '/views', 'index', [
            'title' => t('redirects.title'),
            'nav' => 'redirects',
            'wide' => true,
            'styles' => ['admin-pages.css', 'admin-redirects.css'],
            'rules' => RedirectList::rules($db),
            'kept' => RedirectList::kept($db),
            'pages' => RedirectList::pageChoices($db),
            'errors' => $errors,
            'typed' => $typed,
        ], $status);
    }

    private function flash(string $message): void
    {
        $session = $this->container->get('session');
        $session->set('flash', $message);
        $session->set('flash_kind', 'success');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
