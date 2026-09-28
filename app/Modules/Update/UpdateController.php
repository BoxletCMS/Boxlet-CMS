<?php

namespace App\Modules\Update;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\AdminView;
use App\Support\Url;
use RuntimeException;

/**
 * The "Database update needed" screen and its one button (PLAN.md D-019).
 *
 * The button is the only thing in Boxlet that applies a migration. It is a POST, so the
 * router's CSRF check covers it, and it is behind RequireAdmin, so a visitor cannot
 * reach it at all.
 */
final class UpdateController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        $pending = $this->update()->pending();

        return AdminView::render($this->container, __DIR__ . '/views', 'admin/update', [
            'title' => $pending === [] ? t('update.up_to_date_title') : t('update.title'),
            'nav' => '',
            'pending' => $pending,
            'isSqlite' => $this->db()->driver === 'sqlite',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function run(Request $request, string $locale, array $params): Response
    {
        $session = $this->container->get('session');

        try {
            $applied = $this->update()->run();
            $session->set('flash', $applied === []
                ? t('update.done')
                : t('update.applied', ['files' => implode(', ', $applied)]));
        } catch (RuntimeException $e) {
            // The message already names the file that failed, or says the lock is held.
            // Everything applied before it stays recorded, so pressing the button again
            // continues rather than repeats.
            $session->set('flash', $e->getMessage());
        }

        return Response::redirect(Url::admin('update'));
    }

    private function update(): Update
    {
        return $this->container->get('update');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
