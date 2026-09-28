<?php

namespace App\Modules\Admin;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Support\Dates;

/**
 * The full activity log (PLAN.md D-052): what the Overview's "Recent activity" shows the
 * start of, fifty rows to a page, newest first.
 */
final class ActivityController
{
    private const PER_PAGE = 50;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $total = Activity::count($db);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $wanted = $request->query['page'] ?? '1';
        $page = min($pages, max(1, is_string($wanted) && ctype_digit($wanted) ? (int) $wanted : 1));

        return AdminView::render($this->container, __DIR__ . '/views', 'activity', [
            'title' => t('activity.title'),
            'nav' => 'dashboard',
            'styles' => ['admin-activity.css'],
            'rows' => Activity::recent($db, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'zone' => Dates::zone($db),
            'page' => $page,
            'pages' => $pages,
        ]);
    }
}
