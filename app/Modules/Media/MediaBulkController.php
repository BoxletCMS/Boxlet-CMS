<?php

namespace App\Modules\Media;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Pages\PagesBulkController;
use App\Support\Url;

/**
 * SEVERAL PICTURES AND FILES DELETED AT ONCE (PLAN.md D-218, the owner): each ticked one as
 * its own Delete deletes it, its sizes and its original with it (MediaLibrary::delete). One a
 * page still shows is kept, as Delete keeps it, and named with the pages, and the rest are
 * deleted all the same: one picture in use does not hold back forty that are not.
 */
final class MediaBulkController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $library = $this->container->get('media_library');
        $db = $this->container->get('db');
        $deleted = 0;
        $kept = [];
        foreach (PagesBulkController::ids($request) as $id) {
            $row = $library->find($id);
            if ($row === null) {
                continue;
            }
            $result = $library->delete($id);
            if ($result['deleted']) {
                Activity::record($db, 'media', 'deleted', $id, (string) $row['filename']);
                $deleted++;
            } elseif ($result['used_by'] !== []) {
                $kept[] = t('media.bulk.kept_one', ['name' => (string) $row['filename'], 'pages' => implode(', ', $result['used_by'])]);
            }
        }
        $said = $deleted === 0 && $kept === []
            ? t('media.bulk.none')
            : ($deleted > 0 ? t($deleted === 1 ? 'media.bulk.deleted_one' : 'media.bulk.deleted_many', ['count' => (string) $deleted]) : '');
        if ($kept !== []) {
            $said = trim($said . ' ' . t(count($kept) === 1 ? 'media.bulk.kept_heading_one' : 'media.bulk.kept_heading_many', ['count' => (string) count($kept)]) . ' ' . implode('; ', $kept) . '.');
        }
        $session = $this->container->get('session');
        $session->set('flash', $said);
        // One kept, or nothing ticked, is not coloured as a win.
        if ($kept !== [] || $deleted === 0) {
            $session->set('flash_kind', 'warning');
        }

        // Back to the list as it was filtered; a page past its end shows the last.
        $query = array_filter(['q' => $request->input('q'), 'show' => $request->input('show'), 'kind' => $request->input('kind'), 'page' => $request->input('page')]);

        return Response::redirect(Url::withQuery(Url::admin('media'), $query));
    }
}
