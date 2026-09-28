<?php

namespace App\Modules\Stats;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\AdminView;
use App\Support\Dates;
use App\Support\Url;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The Statistics screen (PLAN.md D-051, O-20, D-055): a period or a chosen range, four
 * figures against the period before, the trend, the map, and the tables — or one of those
 * tables in full. Clicking a row narrows the whole screen to it, except in the two place
 * tables, whose rows are counted without the page and so cannot narrow anything.
 *
 * Everything is in the address, so a view can be bookmarked, shared and stepped out of with
 * the browser's Back, and nothing on the screen needs a script (StatsFilter).
 */
final class StatsController
{
    /** The share of visitors one country needs before the map opens on it (D-055). */
    private const MOSTLY = 0.7;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $settings = Tracker::settings($db);
        if (!$settings['enabled']) {
            return Response::redirect(Url::admin('settings') . '#statistics');
        }

        $status = Geo::status((string) $this->container->get('config')->get('app.storage_path'));
        $cityDatabase = $status !== null && $status['cities'];

        $today = new DateTimeImmutable('now', new DateTimeZone(Dates::zone($db)));
        $filter = StatsFilter::fromQuery($request->query, $today);
        // Which of the place tables there is anything to show (D-055): as much as the owner
        // asked to count, and only while the screen is not narrowed to something that table
        // does not hold — a page above all, which it never will.
        $places = $filter->narrowedBeyondPlaces() ? [] : match ($settings['location']) {
            'city' => ['regions', 'cities'],
            'region' => ['regions'],
            default => [],
        };

        // The tables this screen has, in order: the six from stats_views, then whichever
        // place tables there is anything to show.
        $shown = [...array_values(array_diff(array_keys(StatsQuery::DIMENSIONS), array_keys(StatsQuery::PLACES))), ...$places];

        $wanted = is_string($request->query['all'] ?? null) ? $request->query['all'] : '';
        $all = in_array($wanted, $shown, true) || $wanted === 'missing' ? $wanted : null;
        // The owner's floor under the cities travels with the query (D-109).
        $query = new StatsQuery($db, $settings['cityMin']);
        $totals = $query->totals($filter);
        $before = $filter->previous();

        $data = [
            'title' => t('stats.title'),
            'nav' => 'statistics',
            'wide' => true,
            'styles' => ['admin-stats.css', 'admin-stats-chart.css', 'admin-stats-map.css'],
            'filter' => $filter,
            'totals' => $totals,
            // DB-IP's licence asks for credit where its countries are shown (CC BY 4.0).
            'geo' => $status !== null,
        ];

        if ($all === 'missing') {
            return AdminView::render($this->container, __DIR__ . '/views', 'all', $data + [
                'dimension' => 'missing',
                'rows' => [],
                'missing' => $query->missing($filter, null),
            ]);
        }
        if ($all !== null) {
            return AdminView::render($this->container, __DIR__ . '/views', 'all', $data + [
                'dimension' => $all,
                'rows' => $query->top($all, $filter, null, $settings['group']),
                'cityMin' => $settings['cityMin'],
                'missing' => null,
            ]);
        }

        // WHERE THE MAP OPENS (D-055). On one country when the screen is narrowed to it, or
        // when one country holds nearly all the visitors — which is the case the owner
        // raised: a site that serves one country learns nothing from a world where one shape
        // is dark. The address can always ask for the world back.
        $byCountry = array_column(array_map(
            static fn (array $row): array => ['code' => strtoupper($row['value']), 'visitors' => (int) $row['visitors']],
            $query->top('countries', $filter, null),
        ), 'visitors', 'code');
        unset($byCountry['']);
        $all = array_sum($byCountry);
        $one = (string) (array_search(true, array_map(
            static fn (int $n): bool => $all > 0 && $n / $all > self::MOSTLY,
            $byCountry,
        ), true) ?: $filter->narrowed['country'] ?? '');
        $zoom = ($request->query['map'] ?? '') === 'world' ? '' : $one;

        // One day alone is one point, which is not a line: its chart is the week it ends,
        // narrowed the same way. A range past three months is drawn by week.
        $chart = $filter->days() === 1
            ? StatsFilter::fromQuery($filter->asQuery(['period' => '7d'], ['from', 'to']), $today)
            : $filter;

        return AdminView::render($this->container, __DIR__ . '/views', 'statistics', $data + [
            'previous' => $query->totals($filter, $before['from'], $before['to']),
            'series' => $query->series($chart, $chart->days() > 90),
            'chartWeek' => $filter->days() === 1,
            'chartByWeek' => $chart->days() > 90,
            // The world map (O-20): the same countries as the table beside it, shaded, with
            // a dot on every city big enough to name (D-055).
            'map' => Map::draw(
                Map::path((string) $this->container->get('config')->get('app.public_path')),
                $byCountry,
                static fn (string $code): string => Url::admin('statistics') . '?' . http_build_query($filter->asQuery(['country' => $code])),
                // Only where the map is cut to a country: the dots are not drawn on the
                // world, so asking the database for them there would be work for nothing.
                $zoom !== '' && in_array('cities', $places, true) ? (new PlaceQuery($db, $settings['cityMin']))->markers($filter) : [],
                $zoom,
            ),
            // The floor under the cities, for the gathered row and the note (D-109).
            'cityMin' => $settings['cityMin'],
            'mapZoom' => $zoom,
            'mapCountry' => $one,
            // Addresses that are not there, while the owner counts them (O-20).
            'missing' => $settings['missing'] ? $query->missing($filter) : null,
            'missingOn' => $settings['missing'],
            'tables' => array_map(
                static fn (string $dimension): array => $query->top($dimension, $filter, 10, $settings['group']),
                array_combine($shown, $shown),
            ),
            // Why the regions and the cities are not on the screen. Nothing at all where the
            // owner never asked for them and has no database for them either: a line about
            // a setting is only worth the room where it would change something.
            'placesHidden' => match (true) {
                $places !== [] => '',
                $settings['location'] === 'country' => $cityDatabase ? 'off' : '',
                default => 'narrowed',
            },
        ]);
    }
}
