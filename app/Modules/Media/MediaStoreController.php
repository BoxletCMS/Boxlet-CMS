<?php

namespace App\Modules\Media;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\Url;
use Throwable;

/**
 * Admin: putting pictures and files into the library, and finishing a picture's sizes (PLAN.md
 * D-295): split from MediaController, which lists the library, when the upload learned to
 * answer media-upload.js one file at a time. MediaController keeps the budget and the card,
 * which the media browser and MCP use too; its comment says where an upload fails and why.
 */
final class MediaStoreController
{
    public function __construct(private readonly Container $container)
    {
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
     * WITH A SCRIPT, ONE FILE A REQUEST (D-295; the owner, 2026-10-24: seventeen dropped at
     * once, twelve left unfinished, each needing its own Finish, and nothing on screen but
     * "Uploading…"). media-upload.js sends each file on its own, asking for JSON, so each has
     * a whole budget, shows its own progress, and is finished by finish() until complete.
     * The form without a script still posts them all here, as before.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $started = microtime(true);
        $messages = [];
        $stored = 0;
        $made = [];

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
                // Already in the library is not a failure: the script counts it as there (D-295).
                $made[] = ['id' => (int) $result['id'], 'complete' => (string) ($this->library()->find((int) $result['id'])['status'] ?? '') === 'complete', 'duplicate' => true];
                continue;
            }

            $stored++;
            // A file (D-126) has nothing to make; only a picture has sizes.
            $complete = true;
            if ((string) ($this->library()->find((int) $result['id'])['kind'] ?? 'picture') === 'picture') {
                $complete = $this->container->get('media_variants')->generate($result['id'], MediaController::budget($started))['complete'];
            }
            $made[] = ['id' => (int) $result['id'], 'complete' => $complete, 'duplicate' => false];
            Activity::record($this->container->get('db'), 'media', 'uploaded', (int) $result['id'], (string) ($this->library()->find((int) $result['id'])['filename'] ?? $file['name']));
        }

        if (self::wantsJson($request)) {
            // For the script: what was stored and whether it has all its sizes, and why the
            // rest was not, each in the owner's words. No flash: the script says it.
            return Response::json(['stored' => $made, 'messages' => $messages]);
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
            return MediaController::missing();
        }

        $done = $this->container->get('media_variants')->generate($id, MediaController::budget(microtime(true)));
        if (self::wantsJson($request)) {
            // The script asks again until complete (D-295), and draws the thumbnail it now has.
            $row = $this->library()->find($id) ?? $media;

            return Response::json(['id' => $id, 'complete' => $done['complete'], 'thumb' => MediaVariants::url($row, 'thumb')]);
        }
        $this->container->get('session')->set('flash', t('media.finished', ['name' => (string) $media['filename']]));

        return Response::redirect(Url::admin('media'));
    }

    /** A request from media-upload.js, which asks for JSON (D-295). */
    private static function wantsJson(Request $request): bool
    {
        return str_contains((string) $request->header('accept'), 'application/json');
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
