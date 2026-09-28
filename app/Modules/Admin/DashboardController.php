<?php

namespace App\Modules\Admin;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Support\Dates;

final class DashboardController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * The Overview (PLAN.md D-052): the site's figures, each with its context; what changed
     * lately; what is waiting on the owner; and what visitors read most.
     *
     * Every figure is a count over a small table, and every one says what it means — a
     * bare number makes the owner go and find out.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        // The maintenance switch used to live here. It moved to site settings (D-028), next
        // to the message it shows visitors, because the two were edited in two places and
        // only ever make sense together.
        $db = $this->db();
        // A site installed before the sitemap existed, or one whose file was removed, gets
        // it on the owner's next visit here rather than on their next change (D-049). An
        // is_file() per dashboard view is the whole cost.
        $public = (string) (($this->container->get('config')->get('app', []))['public_path'] ?? '');
        if ($public !== '' && !is_file($public . '/sitemap.xml')) {
            \App\Modules\Pages\Sitemap::refresh($this->container);
        }

        $zone = Dates::zone($db);
        $home = $db->one("SELECT id FROM pages WHERE slug = '' ORDER BY id LIMIT 1");

        return AdminView::render($this->container, __DIR__ . '/views', 'dashboard', [
            'title' => t('admin.nav.dashboard'),
            'nav' => 'dashboard',
            'styles' => ['admin-dashboard.css', 'admin-activity.css'],
            'metrics' => Overview::metrics($db, $zone),
            'rows' => Activity::recent($db, 6),
            'zone' => $zone,
            'issues' => Overview::attention($db, $this->container->get('blocks')),
            'mostRead' => Overview::mostRead($db, $zone),
            'homeId' => $home === null ? null : (int) $home['id'],
            'maintenance' => $this->container->get('maintenance')->isOn(),
        ]);
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
