<?php

namespace App\Modules\Media;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Support\Dates;
use App\Support\Url;
use Throwable;

/**
 * Admin: one picture — what it means, what stays in frame when it is cropped, and
 * removing it.
 *
 * Alt text and caption are per locale, because a picture means the same thing in two
 * languages but is described in the reader's. An EMPTY alt is a decision rather than an
 * unfilled field: it says the picture is decoration and a screen reader should pass over
 * it. That is why it is stored as an empty string instead of being left absent.
 */
final class MediaItemController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        $library = $this->library();
        $id = (int) $params['id'];
        $media = $library->find($id);
        if ($media === null) {
            return MediaController::missing();
        }

        // A FILE FOR VISITORS (D-126) has its own short page: what it is, where it is
        // downloaded from, how often it was, and Delete. Nothing a picture's page offers —
        // a preview, a crop, a focal point, a description to read aloud — applies to it.
        if ((string) ($media['kind'] ?? 'picture') === 'file') {
            return AdminView::render($this->container, __DIR__ . '/views', 'admin/file', [
                'title' => (string) $media['filename'],
                'nav' => 'media',
                'styles' => ['admin-media.css'],
                'file' => MediaController::card($media),
                'usedBy' => $library->usedBy($id),
                'added' => Dates::local((string) $media['created_at'], Dates::zone($this->container->get('db'))),
                'mime' => (string) $media['mime'],
            ]);
        }

        return AdminView::render($this->container, __DIR__ . '/views', 'admin/show', [
            'title' => (string) $media['filename'],
            'nav' => 'media',
            // Cropper is loaded HERE and nowhere else: it is 38KB for one dialog on one
            // screen, and the library listing has no use for it (D-026).
            'styles' => ['admin-media.css', 'admin-focal.css', 'vendor/cropper.min.css', 'admin-crop.css'],
            'scripts' => ['media.js', 'vendor/cropper.min.js', 'media-crop.js'],
            'picture' => MediaController::card($media),
            // The uncropped variant, and only that one — see below.
            'preview' => MediaVariants::url($media, 'full'),
            'meta' => MediaMeta::forPicture($this->container->get('db'), $id),
            'locales' => $this->container->get('locales'),
            'usedBy' => $library->usedBy($id),
            // The size check a replacement is refused by before it is sent, as an upload is.
            'limits' => \App\Support\Bytes::limits(),
            'added' => Dates::local((string) $media['created_at'], Dates::zone($this->container->get('db'))),
            'mime' => (string) $media['mime'],
            'focal' => ['x' => (int) $media['focal_x'], 'y' => (int) $media['focal_y']],
        ]);
    }

    /**
     * Moves the point every crop keeps in frame (PLAN.md D-121, back after D-038 took it away:
     * the owner had not seen what it was for, and a picture behind a hero's words — cut to
     * a phone's shape — is what it is for).
     *
     * THE POINT IS CHOSEN ON THE UNCROPPED PICTURE. `full` keeps all of it, so a click on it
     * means what it looks like it means; on a cropped preview the edges are already gone.
     *
     * @param array<string, string> $params
     */
    public function focal(Request $request, string $locale, array $params): Response
    {
        $library = $this->library();
        $id = (int) $params['id'];
        $media = $library->find($id);
        // A file (D-126) is never cut, so it has no point to keep in frame.
        if ($media === null || (string) ($media['kind'] ?? 'picture') !== 'picture') {
            return MediaController::missing();
        }

        $library->moveFocalPoint($id, (int) $request->input('x'), (int) $request->input('y'));
        $finished = $this->container->get('media_remake')->now($id, MediaController::budget(microtime(true)));
        Activity::record($this->container->get('db'), 'media', 'focal', $id, (string) $media['filename']);
        $this->container->get('session')->set('flash', t($finished ? 'media.focal_saved' : 'media.focal_saved_later'));

        return Response::redirect(Url::admin('media', $id));
    }

    /**
     * Saves alt text and caption for every enabled locale in one submit, because they sit
     * on one screen and a per-locale save button would be four buttons doing one job.
     *
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $library = $this->library();
        $id = (int) $params['id'];
        if ($library->find($id) === null) {
            return MediaController::missing();
        }

        foreach ($this->container->get('locales') as $enabled) {
            $code = (string) $enabled['code'];
            MediaMeta::save(
                $this->container->get('db'),
                $id,
                $code,
                trim($request->input('alt_' . $code)),
                trim($request->input('caption_' . $code)),
            );
        }
        Activity::record($this->container->get('db'), 'media', 'described', $id, (string) ($library->find($id)['filename'] ?? ''));
        $this->container->get('session')->set('flash', t('media.meta_saved'));

        return Response::redirect(Url::admin('media', $id));
    }

    /**
     * Puts different bytes behind this picture, keeping its id.
     *
     * Nothing is removed until the new file has been accepted and written, so a refused
     * replacement leaves the picture exactly as it was — which is why the uploader hands
     * back the row as it stood rather than simply reporting success.
     *
     * @param array<string, string> $params
     */
    public function replace(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        // Replacing is a picture's (D-126): a file is deleted and uploaded again.
        if ((string) ($this->library()->find($id)['kind'] ?? '') !== 'picture') {
            return MediaController::missing();
        }

        $file = $request->files['file'] ?? null;
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $problem = MediaUpload::problem($error);
        if ($problem !== null) {
            return $this->saying($problem, $id);
        }

        try {
            $was = $this->container->get('media_upload')->replace(
                $id,
                (string) ($file['tmp_name'] ?? ''),
                (string) ($file['name'] ?? ''),
            );
        } catch (Throwable $e) {
            return $this->saying($e->getMessage(), $id);
        }

        // The old files are of a picture that is no longer at this id.
        $this->library()->forgetVariants($was);
        $this->container->get('media_variants')->generate($id, MediaController::budget(microtime(true)));
        Activity::record($this->container->get('db'), 'media', 'replaced', $id, (string) ($this->library()->find($id)['filename'] ?? ''));

        return $this->saying(t('media.replaced'), $id);
    }

    /**
     * Removes a picture, unless a page still shows it — in which case the refusal names
     * the pages, because a refusal that does not sends someone hunting through the site.
     *
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $row = $this->library()->find($id);
        $name = (string) ($row['filename'] ?? '');
        $result = $this->library()->delete($id);

        // delete() reports the same "not deleted, nothing using it" for a row that was
        // never there, which is the one case that is a 404 rather than a refusal.
        if (!$result['deleted'] && $result['used_by'] === []) {
            return MediaController::missing();
        }

        if (!$result['deleted']) {
            $this->container->get('session')->set('flash', t('media.in_use', ['pages' => implode(', ', $result['used_by'])]));

            return Response::redirect(Url::admin('media', $id));
        }

        Activity::record($this->container->get('db'), 'media', 'deleted', $id, $name);
        $this->container->get('session')->set('flash', t(($row['kind'] ?? 'picture') === 'file' ? 'media.deleted_file' : 'media.deleted'));

        return Response::redirect(Url::admin('media'));
    }

    /**
     * Back to the picture, with something to say about what just happened.
     */
    private function saying(string $message, int $mediaId): Response
    {
        $this->container->get('session')->set('flash', $message);

        return Response::redirect(Url::admin('media', $mediaId));
    }

    private function library(): MediaLibrary
    {
        return $this->container->get('media_library');
    }
}
