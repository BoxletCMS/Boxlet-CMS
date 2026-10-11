<?php

namespace App\Modules\Media;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Support\Bytes;
use App\Support\Url;
use Throwable;

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

        return AdminView::render($this->container, __DIR__ . '/views', 'admin/index', [
            'title' => t('media.title'),
            'nav' => 'media',
            'styles' => ['admin-media.css', 'admin-media-table.css'],
            'scripts' => ['media.js', 'auto-continue.js', 'admin-bulk.js'],
            'wide' => true,
            'pictures' => $pictures,
            'rows' => $rows,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'show' => $show,
            'kind' => $kind,
            'bytes' => $bytes,
            'accept' => implode(',', array_map(static fn (string $ext): string => '.' . $ext, MediaFileType::acceptedExtensions())),
            'remakeLeft' => $this->container->get('media_remake')->left(),
            'search' => $search,
            'limits' => Bytes::limits(),
        ]);
    }

    /**
     * Stores each chosen file and makes as many variants as the clock allows.
     *
     * The router has already checked the CSRF token, and has already answered a post PHP
     * discarded for being too large, so by here the request arrived whole.
     *
     * One budget covers the whole batch rather than one per file: five pictures at six
     * seconds each is thirty seconds, and a shared host would kill the request somewhere
     * in the middle of the fourth. What is left over is left incomplete on purpose.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $started = microtime(true);
        $messages = [];
        $stored = 0;

        $chosen = self::chosen($request);
        if ($chosen === []) {
            $messages[] = t('media.no_file');
        }

        foreach ($chosen as $file) {
            $problem = MediaUpload::problem($file['error']);
            if ($problem !== null) {
                // An empty slot among several inputs is normal and not worth reporting;
                // anything else names the file it happened to.
                if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $messages[] = $file['name'] . ': ' . $problem;
                }
                continue;
            }

            try {
                $result = $this->container->get('media_upload')->store($file['tmp_name'], $file['name']);
            } catch (Throwable $e) {
                // A refusal names the file: with several chosen at once, "that is not a
                // picture Boxlet accepts" without a name is not actionable.
                $messages[] = $file['name'] . ': ' . $e->getMessage();
                continue;
            }

            if ($result['duplicate']) {
                $messages[] = t('media.duplicate', ['name' => $file['name']]);
                continue;
            }

            $stored++;
            // A file (D-126) has nothing to make; only a picture has sizes.
            if ((string) ($this->library()->find((int) $result['id'])['kind'] ?? 'picture') === 'picture') {
                $this->container->get('media_variants')->generate($result['id'], self::budget($started));
            }
            Activity::record($this->container->get('db'), 'media', 'uploaded', (int) $result['id'], (string) ($this->library()->find((int) $result['id'])['filename'] ?? $file['name']));
        }

        if ($stored > 0) {
            array_unshift($messages, t('media.uploaded', ['count' => (string) $stored]));
        }
        $this->container->get('session')->set('flash', implode(' ', $messages));

        return Response::redirect(Url::admin('media'));
    }

    /**
     * Makes the variants a cut-off upload never reached.
     *
     * One picture per press, deliberately: a host that killed the first request would
     * kill an identical second one, and pressing Finish twice should make progress twice
     * rather than repeat the same failure.
     *
     * @param array<string, string> $params
     */
    public function finish(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $media = $this->library()->find($id);
        if ($media === null) {
            return self::missing();
        }

        $this->container->get('media_variants')->generate($id, self::budget(microtime(true)));
        $this->container->get('session')->set('flash', t('media.finished', ['name' => (string) $media['filename']]));

        return Response::redirect(Url::admin('media'));
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

    /**
     * The chosen files as a plain list.
     *
     * PHP hands a multiple upload over as arrays inside ONE entry — name[0], tmp_name[0]
     * — rather than as a list of files, so every caller that does not flatten it reads
     * the first file and silently ignores the rest.
     *
     * @return list<array{name: string, tmp_name: string, error: int}>
     */
    private static function chosen(Request $request): array
    {
        $entry = $request->files['files'] ?? null;
        if (!is_array($entry) || !isset($entry['error'])) {
            return [];
        }

        $errors = is_array($entry['error']) ? $entry['error'] : [$entry['error']];
        $names = is_array($entry['name'] ?? null) ? $entry['name'] : [$entry['name'] ?? ''];
        $temporary = is_array($entry['tmp_name'] ?? null) ? $entry['tmp_name'] : [$entry['tmp_name'] ?? ''];

        $files = [];
        foreach (array_keys($errors) as $index) {
            $files[] = [
                'name' => (string) ($names[$index] ?? ''),
                'tmp_name' => (string) ($temporary[$index] ?? ''),
                'error' => (int) $errors[$index],
            ];
        }

        return $files;
    }

    private function library(): MediaLibrary
    {
        return $this->container->get('media_library');
    }
}
