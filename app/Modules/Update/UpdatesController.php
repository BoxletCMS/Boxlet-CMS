<?php

namespace App\Modules\Update;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Backup\Backup;
use App\Modules\Backup\Backups;
use App\Modules\Backup\Restore;
use App\Modules\Media\MediaController;
use App\Support\Bytes;
use App\Support\Url;
use RuntimeException;
use Throwable;

/**
 * Admin: updating Boxlet to a new version (PLAN.md D-140), from GitHub or from a ZIP.
 *
 * Two jobs in a row, pressed on by auto-continue.js as the backups are: a backup of the site
 * as it is, then the update (Upgrade). The version waiting on the backup is named in
 * pending.json. After the swap the next request is the new version's own code, which is why
 * everything the steps need lives in files, not in this request.
 */
final class UpdatesController
{
    private const UNLIMITED_STEP = 20.0;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        $session = $this->container->get('session');
        $working = $this->working();

        return AdminView::render($this->container, __DIR__ . '/views', 'admin/updates', [
            'title' => t('updates.title'),
            'nav' => 'updates',
            'styles' => ['admin-backups.css'],
            'scripts' => $working ? ['auto-continue.js'] : [],
            'current' => $this->container->get('version'),
            'working' => $working,
            'progress' => $session->get('update_progress'),
            'found' => $session->get('update_found'),
            'last' => $this->upgrade()->state(),
            'canRollBack' => in_array($this->upgrade()->state()['phase'] ?? null, ['done'], true) && is_dir($this->upgrade()->dir() . '/old'),
            // The largest ZIP this server takes in one upload: the smaller of PHP's two limits.
            'limit' => min(Bytes::limits()['file'], Bytes::limits()['request']),
        ]);
    }

    /**
     * Asks GitHub for a newer release, once, because the owner pressed the button.
     *
     * @param array<string, string> $params
     */
    public function check(Request $request, string $locale, array $params): Response
    {
        $session = $this->container->get('session');
        $session->remove('update_found');
        try {
            $found = $this->releases()->newest($this->container->get('version'));
            if ($found === null) {
                $this->flash(t('updates.newest'), 'success');
            } else {
                $session->set('update_found', $found);
            }
        } catch (Throwable $e) {
            $this->flash($e->getMessage(), 'error');
        }

        return Response::redirect(Url::admin('updates'));
    }

    /**
     * @param array<string, string> $params
     */
    public function github(Request $request, string $locale, array $params): Response
    {
        $found = $this->container->get('session')->get('update_found');
        if (!is_array($found) || $this->working()) {
            return Response::redirect(Url::admin('updates'));
        }
        /** @var array{version: string, url: string, digest: string, published: string, page: string} $found */
        try {
            $this->prepare();
            $this->releases()->download($found, $this->package());
            $this->begin();
        } catch (Throwable $e) {
            $this->flash($e->getMessage(), 'error');
        }

        return Response::redirect(Url::admin('updates') . '#progress');
    }

    /**
     * @param array<string, string> $params
     */
    public function upload(Request $request, string $locale, array $params): Response
    {
        if ($this->working()) {
            return Response::redirect(Url::admin('updates'));
        }
        $file = $request->files['package'] ?? null;
        try {
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new RuntimeException(t('updates.upload_failed'));
            }
            $this->prepare();
            if (!move_uploaded_file((string) $file['tmp_name'], $this->package())) {
                throw new RuntimeException(t('updates.cannot_write', ['path' => $this->package()]));
            }
            $this->begin();
        } catch (Throwable $e) {
            $this->flash($e->getMessage(), 'error');
        }

        return Response::redirect(Url::admin('updates') . '#progress');
    }

    /**
     * One step of the backup, then of the update waiting on it.
     *
     * @param array<string, string> $params
     */
    public function step(Request $request, string $locale, array $params): Response
    {
        $session = $this->container->get('session');
        $budget = MediaController::budget((float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) ?? self::UNLIMITED_STEP;
        try {
            if ($this->backup()->running() !== null) {
                $result = $this->backup()->step($budget);
                $session->set('update_progress', t('updates.progress_backup', [
                    'files' => $result['filesDone'] . ' / ' . $result['files'],
                ]));
                if ($result['done']) {
                    (new Backups($this->container->get('backups_path')))->prune('update');
                    $pending = is_file($this->pending()) ? json_decode((string) file_get_contents($this->pending()), true) : null;
                    if (is_array($pending)) {
                        unlink($this->pending());
                        $this->upgrade()->start((string) $pending['version'], (string) $pending['from'], $result['name']);
                    }
                }
            } elseif (is_file($this->pending()) && !$this->upgrade()->running()) {
                // Named, and its backup gone without finishing (deleted, or a failed step):
                // nothing is under way, so nothing is waited for.
                unlink($this->pending());
                $this->flash(t('updates.lost'), 'error');
            } elseif ($this->upgrade()->running()) {
                $result = $this->upgrade()->step(microtime(true) + $budget);
                $session->set('update_progress', t('updates.phase.' . $result['phase']));
                if ($result['done']) {
                    $state = (array) $this->upgrade()->state();
                    $session->remove('update_progress');
                    $session->remove('update_found');
                    Activity::record($this->container->get('db'), 'update', 'done', null, (string) ($state['version'] ?? ''));
                    $this->flash(t('updates.done', ['version' => (string) ($state['version'] ?? '')]), 'success');
                }
            }
        } catch (Throwable $e) {
            $session->remove('update_progress');
            $this->flash(t('updates.failed', ['error' => $e->getMessage()]), 'error');
        }

        return Response::redirect(Url::admin('updates') . '#progress');
    }

    /**
     * The old code back, and the backup made before the update restored, which the Backups
     * screen carries on with.
     *
     * @param array<string, string> $params
     */
    public function rollBack(Request $request, string $locale, array $params): Response
    {
        $state = $this->upgrade()->state();
        if (($state['phase'] ?? null) !== 'done' || $this->working()) {
            return Response::redirect(Url::admin('updates'));
        }
        $this->upgrade()->rollBack();
        Activity::record($this->container->get('db'), 'update', 'rolled_back', null, (string) ($state['from'] ?? ''));
        $restore = $this->container->get('restore');
        if ($restore instanceof Restore && $restore->problem((string) $state['backup']) === null) {
            $restore->start((string) $state['backup']);
            $this->container->get('session')->set('restoring', (string) $state['backup']);

            return Response::redirect(Url::admin('backups') . '#progress');
        }
        $this->flash(t('updates.rolled_back_code'), 'success');

        return Response::redirect(Url::admin('updates'));
    }

    /** The folder the package goes to, made, and anything left from a failed try removed. */
    private function prepare(): void
    {
        $dir = $this->upgrade()->dir();
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException(t('updates.cannot_write', ['path' => $dir]));
        }
        if (is_file($this->package())) {
            unlink($this->package());
        }
    }

    /** The package checked, and the backup that comes before the update begun. */
    private function begin(): void
    {
        $current = $this->container->get('version');
        try {
            $version = Package::check($this->package(), $current);
        } catch (Throwable $e) {
            unlink($this->package());
            throw $e;
        }
        file_put_contents($this->pending(), json_encode(['version' => $version, 'from' => $current], JSON_THROW_ON_ERROR));
        $this->backup()->start('update');
    }

    private function working(): bool
    {
        return $this->backup()->running() !== null || $this->upgrade()->running() || is_file($this->pending());
    }

    private function package(): string
    {
        return $this->upgrade()->dir() . '/package.zip';
    }

    private function pending(): string
    {
        return $this->upgrade()->dir() . '/pending.json';
    }

    private function flash(string $message, string $kind): void
    {
        $session = $this->container->get('session');
        $session->set('flash', $message);
        $session->set('flash_kind', $kind);
    }

    private function backup(): Backup
    {
        return $this->container->get('backup');
    }

    private function upgrade(): Upgrade
    {
        return $this->container->get('upgrade');
    }

    private function releases(): Releases
    {
        return $this->container->get('releases');
    }
}
