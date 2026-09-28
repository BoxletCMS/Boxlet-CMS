<?php

namespace App\Modules\Stats;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Support\Dates;
use App\Support\Url;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Taking the counts out (PLAN.md O-20): a CSV of one table as the screen is showing it,
 * and a JSON file of everything.
 *
 * OUT ONLY. There is no way to put counts back in, at the owner's decision (2026-09-20):
 * a file of numbers that adds to what a site has counted is a way to make a site's own
 * statistics say something that never happened, and nobody had asked for it.
 */
final class StatsDataController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function export(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $settings = Tracker::settings($db);
        if (!$settings['enabled']) {
            return Response::redirect(Url::admin('settings') . '#statistics');
        }

        $today = new DateTimeImmutable('now', new DateTimeZone(Dates::zone($db)));
        $filter = StatsFilter::fromQuery($request->query, $today);
        $table = is_string($request->query['table'] ?? null) ? $request->query['table'] : '';

        if ($table === 'everything') {
            return self::file(StatsExport::json($db), 'application/json', 'boxlet-statistics-' . $today->format('Y-m-d') . '.json');
        }
        if (!isset(StatsQuery::DIMENSIONS[$table]) && $table !== 'missing') {
            return Response::redirect(Url::admin('statistics'));
        }

        return self::file(
            StatsExport::csv($db, $table, $filter, $settings['group'], $settings['cityMin']),
            'text/csv; charset=utf-8',
            sprintf('boxlet-%s-%s-%s.csv', $table, $filter->from, $filter->to),
        );
    }

    private static function file(string $body, string $type, string $name): Response
    {
        return new Response($body, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
