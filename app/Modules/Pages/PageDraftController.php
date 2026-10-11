<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;

/**
 * THE DRAFT AS JSON (PLAN.md D-163, D-173, README 2.2): what the builder autosaves, a whole page
 * as one document, and what it reads back.
 *
 * `POST /admin/pages/{id}/draft` takes `{"version": n, "document": {…}}` with the CSRF token in
 * an X-CSRF-Token header, and answers `{"version", "savedAt"}`; a version the draft has moved
 * past answers 409 with the draft's own, so the editor can say the page changed elsewhere and
 * offer to reload. No form, so no max_input_vars: a page is one field however long it is.
 *
 * A draft may be incomplete — an empty title, an address being typed — and is saved as it is:
 * Publish is where a page is checked (PagePublish::problems).
 */
final class PageDraftController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $page = Page::find($this->db(), $id);
        $current = $page === null ? null : PageDraft::current($this->db(), $this->registry(), $id);
        if ($page === null || $current === null) {
            return Response::json(['error' => t('pages.not_found')], 404);
        }

        return Response::json([
            'version' => $current['version'],
            'state' => PageDraft::state($this->db(), $page),
            'document' => $current['document'],
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $id = (int) $params['id'];
        $page = Page::find($db, $id);
        if ($page === null) {
            return Response::json(['error' => t('pages.not_found')], 404);
        }
        $from = $request->body['version'] ?? null;
        $document = PageDocument::read($this->registry(), $request->body['document'] ?? null);
        if (!is_int($from) || $from < 0 || $document === null) {
            return Response::json(['error' => t('pages.draft.unreadable')], 422);
        }
        // Whether the page is on the site is the page's, never the draft's to say.
        $document['status'] = (string) $page['status'];

        $version = PageDraft::save($db, $id, $document, $from, PagePublish::admin($this->container));
        if ($version === null) {
            $current = PageDraft::current($db, $this->registry(), $id);

            return Response::json(['error' => t('pages.draft.conflict'), 'version' => $current['version'] ?? 0], 409);
        }

        return Response::json(['version' => $version, 'savedAt' => gmdate('Y-m-d\TH:i:s\Z')]);
    }

    /**
     * Publish, as the builder asks for it (D-174): the draft is checked and written, and the
     * answer says what stops it, field by field, for the bar to show.
     *
     * @param array<string, string> $params
     */
    public function publish(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $page = Page::find($this->db(), $id);
        if ($page === null) {
            return Response::json(['error' => t('pages.not_found')], 404);
        }
        $errors = PagePublish::publish($this->container, $id);
        // Published, it is published with no draft; refused, nothing about it changed.
        $state = $errors === [] ? 'published' : PageDraft::state($this->db(), $page);

        return Response::json(['ok' => $errors === [], 'errors' => $errors, 'state' => $state] + $this->current($id), $errors === [] ? 200 : 422);
    }

    /**
     * Discard, back to the published page; the answer carries that page, to draw.
     *
     * @param array<string, string> $params
     */
    public function discard(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $done = PagePublish::discard($this->container, $id);
        $page = Page::find($this->db(), $id) ?? [];

        return Response::json(['ok' => $done, 'state' => PageDraft::state($this->db(), $page)] + $this->current($id), $done ? 200 : 409);
    }

    /**
     * A revision into the draft (README 2.1): `{"revision": id, "version": n}`.
     *
     * @param array<string, string> $params
     */
    public function restore(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $revision = $request->body['revision'] ?? null;
        $from = $request->body['version'] ?? null;
        if (!is_int($revision) || !is_int($from) || !PagePublish::restore($this->container, $id, $revision, $from)) {
            return Response::json(['error' => t('pages.restore_gone')] + $this->current($id), 409);
        }
        $page = Page::find($this->db(), $id) ?? [];

        return Response::json(['ok' => true, 'state' => PageDraft::state($this->db(), $page)] + $this->current($id));
    }

    /**
     * The draft as a visitor would get it once published: the whole page, header and footer,
     * for "open preview in a new tab". Never indexed, never kept by the page cache (an admin
     * address), and drawn by the visitor's own drawing.
     *
     * @param array<string, string> $params
     */
    public function preview(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $page = Page::find($this->db(), $id);
        $current = $page === null ? null : PageDraft::current($this->db(), $this->registry(), $id);
        if ($page === null || $current === null) {
            return PagesController::missing();
        }
        $pageLocale = (string) $page['locale'];
        $drawn = PageRender::draw($this->db(), $this->registry(), $current['document'], $pageLocale, \App\Modules\Design\Composition::active($this->db()), (string) $this->container->get('config')->get('app.key'), ['pageId' => $id]);
        $head = ['title' => $current['document']['title'], 'description' => '', 'canonical' => null, 'noindex' => true, 'first_surface' => $drawn['firstSurface']];
        $data = ['blocksHtml' => $drawn['html']] + PageLayoutData::forPage($this->container, $pageLocale, $head, $page);

        return Response::html((new \App\Core\View(__DIR__ . '/views'))->render('page', $pageLocale, $data));
    }

    /**
     * The document an editor now works on, and its version.
     *
     * @return array{version: int, document: mixed}
     */
    private function current(int $id): array
    {
        $current = PageDraft::current($this->db(), $this->registry(), $id);

        return ['version' => $current['version'] ?? 0, 'document' => $current['document'] ?? null];
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
