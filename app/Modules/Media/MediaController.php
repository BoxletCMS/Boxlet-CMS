<?php

namespace App\Modules\Media;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\AdminView;
use App\Support\Bytes;
use App\Support\Url;

/**
 * Admin: the picture library — what is in it, and putting more in.
 *
 * One picture on its own is MediaItemController. This is the list and the upload.
 *
 * UPLOADING IS THE PART THAT FAILS ON A REAL SERVER, and it fails in three different
 * places. Each one has to say something a person can act on:
 *
 *   nginx        refuses a body over client_max_body_size — 1 MB by default — and answers
 *                413 itself, before PHP runs at all. Nothing here can catch that, which
 *                is why media.js checks the size in the browser and says so rather than
 *                letting the submit produce a server error page nobody can read.
 *   PHP          discards a post over post_max_size entirely: $_POST and $_FILES both
 *                arrive empty. The router tells that case from an expired form, because
 *                the missing CSRF token is a symptom and not the cause.
 *   the encoder  runs out of execution time part-way through the variants. That is what
 *                the budget below and the Finish button are for: a picture that did not
 *                finish is listed as unfinished, rather than silently half-made.
 */
final class MediaController
{
    /** How many rows the library's list shows at a time. */
    public const PER_PAGE = 50;

    /** Kept back for the redirect itself, after the last encode. */
    private const RESERVE_SECONDS = 3.0;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        $search = $request->query['q'] ?? '';
        $search = is_string($search) ? trim($search) : '';
        // Pictures, files, or both (D-126). The media browser has its own listing now
        // (MediaPickController, D-145), which renders the same cards.
        $kind = in_array($request->query['kind'] ?? '', ['pictures', 'files'], true) ? (string) $request->query['kind'] : '';
        $only = $kind === 'pictures' ? 'picture' : ($kind === 'files' ? 'file' : null);

        $pictures = [];
        foreach ($this->library()->all($search, null, $only) as $row) {
            $pictures[] = self::card($row);
        }

        // The library's table (D-052): what uses each picture, whether it is described in
        // the site's main language, and the filters those two answer.
        $usage = $this->library()->usage();
        // A guessed description counts as set: the owner took the "check it" mark off the
        // library in D-038, and a table column is not the way to bring it back.
        $described = [];
        foreach ($this->container->get('db')->all('SELECT media_id, alt FROM media_meta WHERE locale = ?', [Url::primaryLocale()]) as $row) {
            $described[(int) $row['media_id']] = (string) $row['alt'] === '' ? 'missing' : 'set';
        }
        $show = in_array($request->query['show'] ?? '', ['unused', 'undescribed'], true) ? (string) $request->query['show'] : '';
        $bytes = 0;
        $rows = [];
        foreach ($pictures as $picture) {
            $bytes += $picture['bytes'];
            $row = $picture + [
                'pages' => $usage[$picture['id']]['pages'] ?? 0,
                'site' => $usage[$picture['id']]['site'] ?? false,
                // A file has no description to miss (D-126).
                'described' => $picture['kind'] === 'file' ? 'none' : ($described[$picture['id']] ?? 'missing'),
            ];
            if (($show === 'unused' && ($row['pages'] > 0 || $row['site']))
                || ($show === 'undescribed' && $row['described'] !== 'missing')) {
                continue;
            }
            $rows[] = $row;
        }

        // FIFTY A PAGE (the owner, 2026-09-27). The list stopped at the newest 200 without a
        // word, so a picture past that was reachable only by searching for it. A page past
        // the last shows the last, and the filters and the search travel with the page.
        $total = count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $asked = $request->query['page'] ?? '1';
        $page = min($pages, max(1, is_string($asked) && ctype_digit($asked) ? (int) $asked : 1));
        $rows = array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        // A LIST OR A GRID (PLAN.md O-75): asked for in the address, and kept for the rest of
        // the admin's session, so the library opens the way it was left. The same rows, the
        // same filters, ticks and pages either way.
        $session = $this->container->get('session');
        $asked = $request->query['view'] ?? null;
        if ($asked === 'list' || $asked === 'grid') {
            $session->set('media_view', $asked);
        }
        $view = $session->get('media_view') === 'grid' ? 'grid' : 'list';

        return AdminView::render($this->container, __DIR__ . '/views', 'admin/index', [
            'title' => t('media.title'),
            'nav' => 'media',
            'styles' => ['admin-media.css', 'admin-media-table.css', 'admin-media-grid.css', 'admin-media-upload.css'],
            'scripts' => ['media.js', 'media-upload.js', 'auto-continue.js', 'admin-bulk.js'],
            'wide' => true,
            'pictures' => $pictures,
            'rows' => $rows,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'view' => $view,
            'show' => $show,
            'kind' => $kind,
            'bytes' => $bytes,
            'accept' => implode(',', array_map(static fn (string $ext): string => '.' . $ext, MediaFileType::acceptedExtensions())),
            'remakeLeft' => $this->container->get('media_remake')->left(),
            'search' => $search,
            'limits' => Bytes::limits(),
        ]);
    }

    public static function missing(): Response
    {
        return Response::admin(e(t('media.not_found')), 404);
    }

    /**
     * What a list entry shows for one picture, including the thumbnail to draw it with.
     *
     * @param array<string, mixed> $row
     * @return array{id: int, filename: string, original: string, ext: string, size: string, bytes: int, width: int, height: int, complete: bool, thumb: string|null, kind: string, downloads: int, download: string}
     */
    public static function card(array $row): array
    {
        $ext = strtolower(pathinfo((string) $row['path'], PATHINFO_EXTENSION));

        return [
            'id' => (int) $row['id'],
            'filename' => (string) $row['filename'],
            'original' => (string) $row['original_name'],
            'ext' => $ext,
            'size' => Bytes::human((int) $row['size']),
            'bytes' => (int) $row['size'],
            'width' => (int) $row['width'],
            'height' => (int) $row['height'],
            'complete' => (string) $row['status'] === 'complete',
            // The admin asks for a single file rather than a <picture> with sources: this
            // is a 200px square in a list, the saving would be a few kilobytes, and format
            // negotiation belongs to the front end where the bytes actually matter.
            'thumb' => MediaVariants::url($row, 'thumb'),
            // A file for visitors (D-126): what it is, how often it was taken, and the address
            // it is taken from.
            'kind' => (string) ($row['kind'] ?? 'picture'),
            'downloads' => (int) ($row['downloads'] ?? 0),
            'download' => DownloadController::url((int) $row['id'], (string) $row['filename']),
        ];
    }

    /**
     * How long variant generation may run in this request, or null where PHP sets no
     * limit at all.
     *
     * Read here rather than inside MediaVariants because this is the request that has the
     * deadline; the generator is handed a number and does not care where it came from.
     * That separation is what lets the tests drive it with a budget of their choosing on
     * a machine whose max_execution_time is 0.
     */
    public static function budget(float $startedAt): ?float
    {
        $limit = (int) ini_get('max_execution_time');
        if ($limit <= 0) {
            return null;
        }

        return max(1.0, $limit - (microtime(true) - $startedAt) - self::RESERVE_SECONDS);
    }

    private function library(): MediaLibrary
    {
        return $this->container->get('media_library');
    }
}
