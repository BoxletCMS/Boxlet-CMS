<?php

namespace App\Modules\Admin;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Search across the admin (PLAN.md D-052): a screen of its own, which is what the rail's
 * Search opens without a script, and the same results as a fragment, which is what the ⌘K
 * palette asks for and puts in its list — HTML rather than JSON, as the picture picker's
 * fragment is, so the palette and the screen can never show different things.
 */
final class SearchController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        $query = is_string($request->query['q'] ?? null) ? mb_substr(trim($request->query['q']), 0, 100) : '';
        $results = Search::find($this->container->get('db'), $query);

        if (($request->query['fragment'] ?? '') !== '') {
            return Response::admin((new View(__DIR__ . '/views'))->render('search-results', $locale, [
                'results' => $results,
                'query' => $query,
            ], null));
        }

        return AdminView::render($this->container, __DIR__ . '/views', 'search', [
            'title' => t('search.title'),
            'nav' => 'search',
            'styles' => ['admin-palette.css'],
            'results' => $results,
            'query' => $query,
        ]);
    }
}
