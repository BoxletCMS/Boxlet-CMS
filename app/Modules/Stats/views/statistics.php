<?php

use App\Modules\Stats\Chart;
use App\Modules\Stats\StatsQuery;
use App\Modules\Stats\StatsView;
use App\Support\Url;

/**
 * The Statistics screen (PLAN.md D-051). Provided by AdminView::render().
 *
 * @var string $title
 * @var \App\Modules\Stats\StatsFilter $filter what the screen is showing
 * @var array{visitors: int, views: int, perVisitor: float, mobile: float} $totals
 * @var array{visitors: int, views: int, perVisitor: float, mobile: float} $previous
 * @var list<array{day: string, visitors: int, views: int}> $series
 * @var int $cityMin how many visitors a city needs before it is named (D-109)
 * @var bool $chartWeek whether the chart shows the week the period ends, not the period
 * @var bool $chartByWeek whether the chart's points are weeks rather than days
 * @var array<string, list<array{value: string, visitors: int|null, views: int}>> $tables
 * @var string $placesHidden why the regions and cities are not here: 'narrowed', 'off' or ''
 * @var list<array{path: string, source: string, views: int}>|null $missing the 404s; null while narrowed past what they can answer
 * @var bool $missingOn whether addresses that are not there are counted at all
 * @var string $map the world map, drawn and shaded; '' when the file is missing
 * @var string $mapZoom the country the map is cut to, or '' for the whole world
 * @var string $mapCountry the country it could be cut to, whether or not it is
 * @var bool $geo whether countries come from DB-IP's database, which asks to be credited
 * @var string $csrf
 */
$all = null;
$figures = [
    ['visitors', number_format($totals['visitors']), StatsView::change(StatsQuery::change($totals['visitors'], $previous['visitors']))],
    ['views', number_format($totals['views']), StatsView::change(StatsQuery::change($totals['views'], $previous['views']))],
    ['per_visitor', number_format($totals['perVisitor'], 1), StatsView::change(StatsQuery::change($totals['perVisitor'], $previous['perVisitor']))],
    ['mobile', StatsView::percent($totals['mobile']), '<span class="stats-change">' . e(t('stats.before', ['value' => StatsView::percent($previous['mobile'])])) . '</span>'],
];
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
<?php require __DIR__ . '/periods.php'; ?>

        <ul class="stats-figures" role="list">
<?php foreach ($figures as [$key, $value, $change]): ?>
            <li class="stats-figure">
                <span class="metric-label"><?= e(t('stats.figure.' . $key)) ?></span>
                <span class="metric-value"><?= e($value) ?></span>
                <?= $change ?>
            </li>
<?php endforeach; ?>
        </ul>

        <section class="panel stats-trend" aria-labelledby="stats-trend-heading">
            <div class="stats-trend-head">
                <h2 id="stats-trend-heading"><?= e(t($chartWeek ? 'stats.trend_week' : ($chartByWeek ? 'stats.trend_weekly' : 'stats.trend'))) ?></h2>
                <p class="stats-legend" aria-hidden="true">
                    <span class="stats-key stats-key-visitors"><?= e(t('stats.visitors')) ?></span>
                    <span class="stats-key stats-key-views"><?= e(t('stats.views')) ?></span>
                </p>
            </div>
            <?= Chart::trend($series, static fn (string $day): string => StatsView::day($day)) ?>
            <details class="stats-numbers">
                <summary><?= e(t('stats.as_table')) ?></summary>
                <table class="stats-table">
                    <thead><tr>
                        <th scope="col"><?= e(t($chartByWeek ? 'stats.week_of' : 'stats.day')) ?></th>
                        <th scope="col" class="stats-num"><?= e(t('stats.visitors')) ?></th>
                        <th scope="col" class="stats-num"><?= e(t('stats.views')) ?></th>
                    </tr></thead>
                    <tbody>
<?php foreach ($series as $point): ?>
                        <tr><th scope="row"><?= e(StatsView::day($point['day'], true)) ?></th><td class="stats-num"><?= e(number_format($point['visitors'])) ?></td><td class="stats-num"><?= e(number_format($point['views'])) ?></td></tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </section>

<?php if ($map !== ''): ?>
        <?php /* The world, shaded by where visitors came from; a country is a link that
                 narrows the screen to it, and the table under it is its text alternative. */ ?>
        <section class="panel stats-map" aria-labelledby="stats-map-heading">
            <div class="stats-trend-head">
                <h2 id="stats-map-heading"><?= e(t('stats.map')) ?></h2>
                <p class="stats-legend">
                    <span class="stats-key stats-key-few" aria-hidden="true"><?= e(t('stats.map_few')) ?></span>
                    <span class="stats-key stats-key-many" aria-hidden="true"><?= e(t('stats.map_many')) ?></span>
<?php if ($mapZoom !== ''): ?>
                    <a href="<?= e(Url::admin('statistics') . '?' . http_build_query($filter->asQuery(['map' => 'world']))) ?>"><?= e(t('stats.map_world')) ?></a>
<?php elseif ($mapCountry !== ''): ?>
                    <a href="<?= e(Url::admin('statistics') . '?' . http_build_query($filter->asQuery([], ['map']))) ?>"><?= e(t('stats.map_one', ['country' => strtoupper($mapCountry)])) ?></a>
<?php endif; ?>
                </p>
            </div>
            <?= $map ?>
        </section>

<?php endif; ?>
        <div class="stats-tables">
<?php foreach ($tables as $dimension => $rows): ?>
            <section class="panel stats-dimension" aria-labelledby="stats-<?= e($dimension) ?>">
                <h2 id="stats-<?= e($dimension) ?>"><?= e(t('stats.table.' . $dimension)) ?></h2>
<?php require __DIR__ . '/table.php'; ?>
                <p class="stats-more">
<?php if (count($rows) >= 10): ?>
                    <a href="<?= e(Url::admin('statistics') . '?' . http_build_query($filter->asQuery(['all' => $dimension]))) ?>"><?= e(t('stats.show_all')) ?></a>
<?php endif; ?>
                    <a class="stats-csv" href="<?= e(Url::admin('statistics', 'export') . '?' . http_build_query($filter->asQuery(['table' => $dimension]))) ?>"><?= e(t('stats.download_csv')) ?></a>
                </p>
            </section>
<?php endforeach; ?>
        </div>
<?php if ($placesHidden !== ''): ?>
        <p class="hint stats-note"><?= e(t('stats.places_' . $placesHidden)) ?></p>
<?php elseif (isset($tables['cities'])): ?>
        <?php /* Under the cities, not under each table: it is about the figures, and one
                 line in the right place is read where two in the wrong one are not. */ ?>
        <p class="hint stats-note"><?= e($cityMin > 1
            ? t('stats.places_note', ['count' => (string) $cityMin])
            : t('stats.places_note_all')) ?></p>
<?php endif; ?>

<?php if ($missingOn): ?>
        <?php /* Addresses that are not there (O-20), counted only while the owner asks for
                 it: the source beside each one says whose link is broken. */ ?>
        <section class="panel stats-dimension" aria-labelledby="stats-missing">
            <h2 id="stats-missing"><?= e(t('stats.table.missing')) ?></h2>
<?php require __DIR__ . '/missing.php'; ?>
            <p class="stats-more">
<?php if ($missing !== null && count($missing) >= 10): ?>
                <a href="<?= e(Url::admin('statistics') . '?' . http_build_query($filter->asQuery(['all' => 'missing']))) ?>"><?= e(t('stats.show_all')) ?></a>
<?php endif; ?>
                <a class="stats-csv" href="<?= e(Url::admin('statistics', 'export') . '?' . http_build_query($filter->asQuery(['table' => 'missing']))) ?>"><?= e(t('stats.download_csv')) ?></a>
            </p>
        </section>

<?php endif; ?>
        <?php /* The counts themselves (O-20): a file out, and no way back in — the owner
                 took the import out, so nothing but a visit can write a count. */ ?>
        <section class="panel stats-data" id="data" aria-labelledby="stats-data-heading">
            <h2 id="stats-data-heading"><?= e(t('stats.data')) ?></h2>
            <p class="hint"><?= e(t('stats.data_intro')) ?></p>
            <div class="form-actions">
                <a class="button button-secondary" href="<?= e(Url::admin('statistics', 'export') . '?' . http_build_query(['table' => 'everything'])) ?>"><?= e(t('stats.download_all')) ?></a>
            </div>
        </section>

        <p class="hint stats-note"><?= e(t('stats.note')) ?> <a href="<?= e(Url::admin('settings') . '#statistics') ?>"><?= e(t('stats.settings_link')) ?></a></p>
<?php if ($geo): ?>
        <p class="hint stats-note"><a href="https://db-ip.com" target="_blank" rel="noopener"><?= e(t('stats.attribution')) ?></a></p>
<?php endif; ?>
