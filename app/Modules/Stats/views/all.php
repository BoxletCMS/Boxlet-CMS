<?php

use App\Support\Url;

/**
 * One of the Statistics screen's tables in full (PLAN.md D-051). Provided by
 * AdminView::render().
 *
 * @var string $title
 * @var \App\Modules\Stats\StatsFilter $filter
 * @var array{visitors: int, views: int, perVisitor: float, mobile: float} $totals
 * @var string $dimension
 * @var list<array{value: string, visitors: int|null, views: int}> $rows
 * @var list<array{path: string, source: string, views: int}>|null $missing the 404s, when that is the table asked for
 */
$all = $dimension;
?>
        <div class="page-header">
            <h1><?= e(t('stats.table.' . $dimension)) ?></h1>
        </div>
        <p class="page-subtitle"><a href="<?= e(Url::admin('statistics') . '?' . http_build_query($filter->asQuery([], ['all']))) ?>"><?= e(t('stats.back')) ?></a></p>
<?php require __DIR__ . '/periods.php'; ?>
        <p class="stats-more"><a class="stats-csv" href="<?= e(Url::admin('statistics', 'export') . '?' . http_build_query($filter->asQuery(['table' => $dimension], ['all']))) ?>"><?= e(t('stats.download_csv')) ?></a></p>

        <section class="panel stats-dimension" aria-label="<?= e(t('stats.table.' . $dimension)) ?>">
<?php if ($dimension === 'missing'): ?>
<?php require __DIR__ . '/missing.php'; ?>
<?php else: ?>
<?php require __DIR__ . '/table.php'; ?>
<?php endif; ?>
        </section>
