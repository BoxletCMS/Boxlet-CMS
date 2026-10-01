<?php

namespace App\Modules\Media;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Admin\Activity;
use App\Support\Bytes;
use RuntimeException;
use Throwable;

/**
 * Admin: the media browser a picture field opens (PLAN.md D-145) — the library's cards a
 * page at a time, and a new picture put into the library from inside the editor, cropped
 * on the way in if the owner asks.
 *
 * Every answer is a fragment of the library's own cards (admin/cards.php), never JSON: the
 * browser shows precisely what the library shows, and what it needs to choose a picture —
 * its id, its name, its thumbnail — is already on the card it chooses by.
 *
 * And an Embed block's cover, taken from the video by the server (poster(), D-147).
 *
 * CROPPING ON UPLOAD SENDS A RECTANGLE, NEVER AN IMAGE, as the library's crop does (D-026).
 * The browser draws the box over the file it is about to send; the server cuts the file it
 * received with MediaCrop and stores only the cut (the owner's choice: the uncropped file is
 * not kept). So there is one way to cut a picture, whichever screen asked for it.
 */
final class MediaPickController
{
    /** How many cards one page of the browser shows; "Show more" asks for the next. */
    public const PER_PAGE = 48;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * One page of pictures, newest first, searched by name or description.
     *
     * Files for visitors (D-126) are never offered: a picture field has nothing to do with
     * a document.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        $search = $request->query['q'] ?? '';
        $search = is_string($search) ? trim($search) : '';
        $asked = $request->query['page'] ?? '1';
        $page = max(1, is_string($asked) && ctype_digit($asked) ? (int) $asked : 1);

        // One more than a page, to know whether there is a next one without counting.
        $rows = $this->library()->all($search, self::PER_PAGE + 1, 'picture', ($page - 1) * self::PER_PAGE);
        $more = count($rows) > self::PER_PAGE;

        return $this->cards(
            array_slice($rows, 0, self::PER_PAGE),
            $search,
            $locale,
            $more ? $page + 1 : null,
            // The first time a screen opens the browser it asks for the dialog around the
            // cards; every later page and search asks for the cards alone.
            ($request->query['dialog'] ?? '') !== '' ? 'admin/browser' : 'admin/pick',
        );
    }

    /**
     * One picture into the library, cut first if a rectangle came with it, and as many of
     * its sizes made as the clock allows. Answers with its card, which chooses it.
     *
     * A refusal is a 422 with the reason as text, which the browser shows where the drop
     * zone was: it names what to do next, as every other upload refusal does.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $started = microtime(true);
        $file = $request->files['file'] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return self::refused(t('media.no_file'));
        }
        $problem = MediaUpload::problem((int) $file['error']);
        if ($problem !== null) {
            return self::refused($problem);
        }

        $name = (string) ($file['name'] ?? '');
        $received = (string) ($file['tmp_name'] ?? '');
        // Only a picture: a document dropped here would otherwise be stored as a file for
        // visitors, which a picture field cannot use and nobody asked for.
        $sniffed = MediaFileType::sniff($received);
        if (MediaFileType::documentExtension($name, $sniffed) !== null) {
            return self::refused(t('picker.not_picture'));
        }

        $cut = null;
        try {
            if ($request->input('crop') === '1') {
                $cut = $this->cut($received, $name, $request);
            }
            $result = $this->container->get('media_upload')->store($cut ?? $received, $name);
        } catch (Throwable $e) {
            return self::refused($e->getMessage());
        } finally {
            if ($cut !== null && is_file($cut)) {
                @unlink($cut);
            }
        }

        return $this->kept($result, $name, $started, $locale);
    }

    /**
     * An Embed block's cover: the video's own still, fetched once by the server from the
     * address the owner pasted, and put into the library like any upload (D-147). The
     * visitor never asks YouTube or Vimeo for it; the site serves its own copy.
     *
     * @param array<string, string> $params
     */
    public function poster(Request $request, string $locale, array $params): Response
    {
        $started = microtime(true);
        $temporary = null;
        try {
            $found = $this->container->get('embed_poster')->fetch((string) $request->input('url'));
            $temporary = tempnam((string) $this->container->get('config')->get('app.storage_path'), 'poster');
            if ($temporary === false || file_put_contents($temporary, $found['bytes']) === false) {
                throw new RuntimeException(t('media.storage_unwritable'));
            }
            $result = $this->container->get('media_upload')->store($temporary, $found['name']);
        } catch (Throwable $e) {
            return self::refused($e->getMessage());
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return $this->kept($result, $found['name'], $started, $locale);
    }

    /**
     * What an upload became, answered as its card: its sizes made as far as the clock
     * allows, and the upload recorded. A picture already in the library is simply that
     * picture — choosing it is what uploading it here was for.
     *
     * @param array{id: int, duplicate: bool} $result
     */
    private function kept(array $result, string $name, float $started, string $locale): Response
    {
        $id = (int) $result['id'];
        if (!$result['duplicate']) {
            $this->container->get('media_variants')->generate($id, MediaController::budget($started));
            Activity::record($this->container->get('db'), 'media', 'uploaded', $id, (string) ($this->library()->find($id)['filename'] ?? $name));
        }

        return $this->one($id, $locale);
    }

    /**
     * Makes the sizes an upload ran out of time for, one request at a time, and answers
     * with the card again. The browser asks until the card says the picture is complete,
     * so a picture chosen from the editor is never left half-made.
     *
     * @param array<string, string> $params
     */
    public function finish(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $media = $this->library()->find($id);
        if ($media === null || (string) ($media['kind'] ?? 'picture') !== 'picture') {
            return MediaController::missing();
        }
        $this->container->get('media_variants')->generate($id, MediaController::budget(microtime(true)));

        return $this->one($id, $locale);
    }

    /**
     * The received file cut to the rectangle the owner dragged, as a temporary file in
     * storage. Anything wrong with the rectangle is refused by MediaCrop, in its words.
     */
    private function cut(string $received, string $name, Request $request): string
    {
        $extension = MediaFileType::extensionFor($name, MediaFileType::sniff($received));
        $encoder = $this->container->get('media_encoder');
        // What store() would refuse, refused before cutting, so the reason is its own and
        // not an encoder's failure to read a file that was never a picture.
        if ($extension === null) {
            throw new RuntimeException(t('picker.not_picture'));
        }
        if ($encoder->driver() === null) {
            throw new RuntimeException(t('media.refused_no_encoder'));
        }
        if ($extension === 'avif' && !$encoder->supports('avif')) {
            throw new RuntimeException(t('media.refused_avif'));
        }

        // The browser measured the file as it shows it, upright, and inspect() measures it
        // the same way, so the rectangle and the picture share one coordinate system.
        $size = $encoder->inspect($received);
        $rect = MediaCrop::rectangle(['width' => $size['width'], 'height' => $size['height']], [
            'x' => (int) $request->input('x'),
            'y' => (int) $request->input('y'),
            'width' => (int) $request->input('w'),
            'height' => (int) $request->input('h'),
            'fullWidth' => (int) $request->input('full_w'),
            'fullHeight' => (int) $request->input('full_h'),
        ], (string) $request->input('ratio'));

        return MediaCrop::cut(
            $this->container->get('media_writer'),
            $received,
            (string) $this->container->get('config')->get('app.storage_path'),
            $rect,
            MediaEncoder::orientationOf($received),
            $extension,
        );
    }

    private function one(int $id, string $locale): Response
    {
        $row = $this->library()->find($id);
        if ($row === null) {
            return MediaController::missing();
        }

        return $this->cards([$row], '', $locale, null);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function cards(array $rows, string $search, string $locale, ?int $next, string $view = 'admin/pick'): Response
    {
        return Response::admin((new View(__DIR__ . '/views'))->render($view, $locale, [
            'pictures' => array_map([MediaController::class, 'card'], $rows),
            'search' => $search,
            'picking' => true,
            'next' => $next,
            'csrf' => $this->container->get('session')->csrfToken(),
            'limits' => Bytes::limits(),
        ], null));
    }

    private static function refused(string $message): Response
    {
        return Response::admin(e($message), 422);
    }

    private function library(): MediaLibrary
    {
        return $this->container->get('media_library');
    }
}
