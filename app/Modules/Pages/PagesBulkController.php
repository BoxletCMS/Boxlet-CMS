<?php

namespace App\Modules\Pages;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\Url;

/**
 * SEVERAL PAGES DELETED AT ONCE (PLAN.md D-218, the owner): the pages ticked in the list, each
 * deleted as the row's own Delete deletes it — its blocks, drafts and revisions with it, its
 * old addresses and its menu items (Page::delete) — and recorded one by one in the activity.
 * The way the owner clears the demo, with Media's after it: a picture is free to delete once
 * no page shows it.
 */
final class PagesBulkController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $deleted = 0;
        foreach (self::ids($request) as $id) {
            $page = Page::find($db, $id);
            if ($page === null) {
                continue;
            }
            Page::delete($db, $id);
            Activity::record($db, 'page', 'deleted', $id, (string) $page['title']);
            $deleted++;
        }
        if ($deleted > 0) {
            Sitemap::refresh($this->container);
        }
        $session = $this->container->get('session');
        $session->set('flash', $deleted === 0
            ? t('pages.bulk.none')
            : t($deleted === 1 ? 'pages.bulk.deleted_one' : 'pages.bulk.deleted_many', ['count' => (string) $deleted]));
        if ($deleted === 0) {
            $session->set('flash_kind', 'warning');
        }

        // Back to the list as it was filtered.
        $query = array_filter(['lang' => $request->input('lang'), 'q' => $request->input('q')]);

        return Response::redirect(Url::withQuery(Url::admin('pages'), $query));
    }

    /**
     * The ticked ids, each once, each a positive integer; anything else is ignored.
     *
     * @return list<int>
     */
    public static function ids(Request $request): array
    {
        $raw = $request->body['ids'] ?? [];
        $ids = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        return array_values($ids);
    }
}
