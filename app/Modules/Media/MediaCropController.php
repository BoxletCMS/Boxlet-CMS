<?php

namespace App\Modules\Media;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\Url;
use Throwable;

/**
 * Admin: cutting a picture down to the part worth keeping (PLAN.md D-026).
 *
 * Split from MediaItemController, which reached the 300-line rule when this arrived. The
 * seam is a real one and not a line count: cropping already has its own geometry class
 * (MediaCrop), its own script and its own stylesheet, and what remains in MediaItemController
 * is what a picture MEANS and how it ends. This is the one action that makes a new picture
 * out of an old one.
 *
 * THE BROWSER SENDS A RECTANGLE, NEVER AN IMAGE. The dialog shows the `full` variant,
 * because originals are not public (D-020), so the numbers arrive in that variant's pixels
 * and MediaCrop scales them to the original's before anything is cut. Nothing drawn in a
 * browser is trusted as a pixel.
 */
final class MediaCropController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Cuts the original down to the rectangle the owner dragged.
     *
     * TWO OUTCOMES, DELIBERATELY UNEQUAL. `new` keeps this picture and adds the crop as
     * another, which is safe and undone by deleting the new one. `replace` keeps the id, so
     * every page showing this picture shows the crop — and the original is gone. The wording
     * and the colours carry that difference, because a confirm dialog is the last thing read
     * and the first thing dismissed.
     *
     * @param array<string, string> $params
     */
    public function crop(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $media = $this->library()->find($id);
        // A file (D-126) has nothing to cut.
        if ($media === null || (string) ($media['kind'] ?? 'picture') !== 'picture') {
            return MediaController::missing();
        }

        $storage = (string) $this->container->get('config')->get('app.storage_path');
        $source = $storage . '/' . (string) $media['path'];
        $replacing = $request->input('action') === 'replace';
        $temporary = null;

        try {
            $rect = MediaCrop::rectangle($media, [
                'x' => (int) $request->input('x'),
                'y' => (int) $request->input('y'),
                'width' => (int) $request->input('w'),
                'height' => (int) $request->input('h'),
                'fullWidth' => (int) $request->input('full_w'),
                'fullHeight' => (int) $request->input('full_h'),
            ], (string) $request->input('ratio'));

            $temporary = MediaCrop::cut(
                $this->container->get('media_writer'),
                $source,
                $storage,
                $rect,
                MediaEncoder::orientationOf($source),
                MediaFileType::extensionFor((string) $media['path'], (string) $media['mime']) ?? 'jpg',
            );

            $focal = MediaCrop::focalAfter($media, $rect);
            $name = (string) $media['filename'] . '-crop.' . pathinfo((string) $media['path'], PATHINFO_EXTENSION);

            $newId = $replacing
                ? $this->cropInPlace($id, $temporary, $name)
                : $this->cropAsNew($id, $temporary, $name);

            @unlink($temporary);
            // Carrying the focal point is also what clears the old variants and marks the
            // row unfinished, so one call does both jobs.
            $this->library()->setFocalPoint($newId, $focal['x'], $focal['y']);
            $this->container->get('media_variants')->generate($newId, MediaController::budget(microtime(true)));
            Activity::record($this->container->get('db'), 'media', 'cropped', $newId, (string) ($this->library()->find($newId)['filename'] ?? ''));

            return $this->saying(t($replacing ? 'media.cropped_replaced' : 'media.cropped_new'), $newId);
        } catch (Throwable $e) {
            if ($temporary !== null && is_file($temporary)) {
                @unlink($temporary);
            }

            return $this->saying($e->getMessage(), $id);
        }
    }

    /**
     * The crop as a NEW picture, through the ordinary upload path so that deduplication and
     * resumable variants behave exactly as they do for any other file.
     */
    private function cropAsNew(int $fromId, string $temporary, string $name): int
    {
        $stored = $this->container->get('media_upload')->store($temporary, $name);
        $newId = (int) $stored['id'];

        // What the picture MEANS travels with it: the crop shows the same subject, so making
        // the owner describe it a second time would be asking them to repeat themselves.
        // copy() rather than save(), because saving would CONFIRM a suggestion nobody has
        // looked at, and the mark has to arrive as it was (D-025, D-026).
        MediaMeta::copy($this->container->get('db'), $fromId, $newId);

        return $newId;
    }

    /**
     * The crop behind the SAME id. Everything that can refuse has already refused by the time
     * this runs, so the old files go only once the new bytes are accepted — replace() hands
     * back the row as it stood, which is what says which files those were.
     */
    private function cropInPlace(int $id, string $temporary, string $name): int
    {
        $was = $this->container->get('media_upload')->replace($id, $temporary, $name);
        $this->library()->forgetVariants($was);

        return $id;
    }

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
