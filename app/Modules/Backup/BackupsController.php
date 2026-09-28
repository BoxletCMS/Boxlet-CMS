<?php

namespace App\Modules\Backup;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Media\MediaController;
use App\Support\Dates;
use App\Support\Url;
use Throwable;

/**
 * Admin: the backups, and putting one back (PLAN.md D-139).
 *
 * Making a backup and restoring one both run in steps: Continue does what fits in one
 * request, and auto-continue.js presses it while there is more, as it does for making every
 * picture's sizes again (D-048). A restore is two jobs in a row: a backup of the site as it
 * is, then the restore. The backup it waits on is named in a file, restore.pending, so the
 * step that finishes the backup knows to begin the restore.
 */
final class BackupsController
{
    private const PENDING = 'restore.pending';

    /** A step's longest run where PHP sets no limit, so the screen still shows progress. */
    private const UNLIMITED_STEP = 20.0;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        $backups = $this->backups()->all();

        return AdminView::render($this->container, __DIR__ . '/views', 'index', [
            'title' => t('backups.title'),
            'nav' => 'backups',
            'wide' => true,
            'styles' => ['admin-backups.css'],
            'scripts' => $this->working() ? ['auto-continue.js'] : [],
            'backups' => $backups,
            'total' => array_sum(array_column($backups, 'size')),
            'working' => $this->working(),
            'restoring' => $this->restorer()->running() || is_file($this->pending()),
            'progress' => $this->container->get('session')->get('backup_progress'),
            'zone' => Dates::zone($this->container->get('db')),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function start(Request $request, string $locale, array $params): Response
    {
        if (!$this->working()) {
            $this->backup()->start('manual');
        }

        return Response::redirect(Url::admin('backups') . '#progress');
    }

    /**
     * One step of whatever is under way: the backup, then the restore waiting on it.
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
                $session->set('backup_progress', t('backups.progress_backup', [
                    'tables' => $result['tablesDone'] . ' / ' . $result['tables'],
                    'files' => $result['filesDone'] . ' / ' . $result['files'],
                ]));
                if ($result['done']) {
                    $this->madeBackup($result['name']);
                }
            } elseif ($this->restorer()->running()) {
                $result = $this->restorer()->step($budget);
                $session->set('backup_progress', t('backups.progress_restore', [
                    'tables' => $result['tablesDone'] . ' / ' . $result['tables'],
                    'files' => $result['entriesDone'] . ' / ' . $result['entries'],
                ]));
                if ($result['done']) {
                    $session->remove('backup_progress');
                    Activity::record($this->container->get('db'), 'backup', 'restored', null, (string) $this->container->get('session')->get('restoring'));
                    $this->flash(t('backups.restored'), 'success');
                }
            }
        } catch (Throwable $e) {
            $session->remove('backup_progress');
            $this->flash(t('backups.failed', ['error' => $e->getMessage()]), 'error');
        }

        return Response::redirect(Url::admin('backups') . '#progress');
    }

    /**
     * What the owner reads before restoring: what will happen, and what it replaces.
     *
     * @param array<string, string> $params
     */
    public function confirm(Request $request, string $locale, array $params): Response
    {
        $name = $params['name'];
        $problem = $this->restorer()->problem($name);
        if ($problem !== null) {
            $this->flash($problem, 'error');

            return Response::redirect(Url::admin('backups'));
        }

        return AdminView::render($this->container, __DIR__ . '/views', 'confirm', [
            'title' => t('backups.restore_title'),
            'nav' => 'backups',
            'styles' => ['admin-backups.css'],
            'backup' => $this->find($name),
            'zone' => Dates::zone($this->container->get('db')),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function restore(Request $request, string $locale, array $params): Response
    {
        $name = $params['name'];
        $problem = $this->restorer()->problem($name);
        if ($problem !== null || $this->working()) {
            $this->flash($problem ?? t('backups.busy'), 'error');

            return Response::redirect(Url::admin('backups'));
        }
        // The backup to put back is named here; the backup of the site as it is goes first.
        file_put_contents($this->pending(), $name);
        $this->container->get('session')->set('restoring', $name);
        $this->backup()->start('restore');

        return Response::redirect(Url::admin('backups') . '#progress');
    }

    /**
     * @param array<string, string> $params
     */
    public function download(Request $request, string $locale, array $params): Response
    {
        $path = $this->backups()->path($params['name']);
        if ($path === null) {
            return Response::redirect(Url::admin('backups'));
        }

        return Response::download($path, 'application/zip', 'boxlet-backup-' . $params['name'] . '.zip');
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        if ($this->backups()->delete($params['name'])) {
            Activity::record($this->container->get('db'), 'backup', 'deleted', null, $params['name']);
            $this->flash(t('backups.deleted'), 'success');
        }

        return Response::redirect(Url::admin('backups'));
    }

    /**
     * A backup just finished: kept, the old automatic ones pruned, and the restore that was
     * waiting on it begun.
     */
    private function madeBackup(string $name): void
    {
        $session = $this->container->get('session');
        $session->remove('backup_progress');
        $kind = (string) substr($name, 18);
        $this->backups()->prune($kind);
        Activity::record($this->container->get('db'), 'backup', 'made', null, $name);

        if (is_file($this->pending())) {
            $restoring = trim((string) file_get_contents($this->pending()));
            unlink($this->pending());
            $this->restorer()->start($restoring);

            return;
        }
        $this->flash(t('backups.made'), 'success');
    }

    /** @return array{name: string, kind: string, size: int, made: string, version: string, readable: bool}|null */
    private function find(string $name): ?array
    {
        foreach ($this->backups()->all() as $backup) {
            if ($backup['name'] === $name) {
                return $backup;
            }
        }

        return null;
    }

    private function working(): bool
    {
        return $this->backup()->running() !== null || $this->restorer()->running();
    }

    private function pending(): string
    {
        return $this->container->get('backups_path') . '/' . self::PENDING;
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

    private function restorer(): Restore
    {
        return $this->container->get('restore');
    }

    private function backups(): Backups
    {
        return new Backups($this->container->get('backups_path'));
    }
}
