<?php

use App\Modules\Stats\StatsFilter;
use App\Modules\Stats\StatsQuery;
use App\Modules\Stats\StatsView;
use App\Support\Url;

/**
 * One dimension as a table: its rows, each with a bar for its share, and the counts. Every
 * value is a link that narrows the whole screen to it (PLAN.md O-20). Required by
 * statistics.php and all.php.
 *
 * The bar is an SVG whose rect is as long as the share: a width in a style attribute would
 * be refused by the admin's CSP, and an attribute on an SVG is not a style.
 *
 * @var string $dimension
 * @var list<array{value: string, visitors: int|null, views: int}> $rows
 * @var array{visitors: int, views: int, perVisitor: float, mobile: float} $totals
 * @var StatsFilter $filter
 * @var string|null $all
 */
$byViews = $dimension === 'pages';
$whole = max(1, $byViews ? $totals['views'] : $totals['visitors']);
$key = StatsView::filterKey($dimension);
$narrow = static fn (string $value): string => Url::admin('statistics') . '?'
    . http_build_query($filter->asQuery([$key => $value === '' ? '-' : $value] + ($all !== null ? ['all' => $all] : [])));
?>
<?php if ($rows === []): ?>
                <p class="hint"><?= e(t('stats.none')) ?></p>
<?php else: ?>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('stats.column.' . $dimension)) ?></th>
                            <th scope="col" class="stats-num"><?= e(t('stats.visitors')) ?></th>
                            <th scope="col" class="stats-num"><?= e(t('stats.views')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($rows as $row):
    $share = min(100, ($byViews ? $row['views'] : (int) $row['visitors']) * 100 / $whole);
    // The gathered row stands for many values at once, so there is nothing to narrow to.
    // Nor is there for a region or a city: they are counted in a table the rest of the
    // screen cannot be narrowed against, which is what keeps the page away from them.
    $narrowed = isset($filter->narrowed[$key]) || $row['value'] === StatsQuery::OTHER
        || isset(StatsQuery::PLACES[$dimension]);
    ?>
                        <tr>
                            <th scope="row" class="stats-name">
                                <svg class="stats-bar" viewBox="0 0 100 1" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect width="<?= e(number_format($share, 1, '.', '')) ?>" height="1"/></svg>
<?php if ($narrowed): ?>
                                <span><?= e(StatsView::label($dimension, $row['value'], $cityMin ?? null)) ?></span>
<?php else: ?>
                                <a href="<?= e($narrow($row['value'])) ?>" title="<?= e(t('stats.narrow_to', ['value' => StatsView::label($dimension, $row['value'], $cityMin ?? null)])) ?>"><?= e(StatsView::label($dimension, $row['value'], $cityMin ?? null)) ?></a>
<?php endif; ?>
<?php if ($byViews && $row['value'] !== ''): ?>
                                <a class="stats-open" href="<?= e(Url::asset(implode('/', array_map('rawurlencode', explode('/', ltrim($row['value'], '/')))))) ?>" target="_blank" rel="noopener" title="<?= e(t('stats.open_page')) ?>"><?= icon('external-link') ?><span class="visually-hidden"><?= e(t('stats.open_page')) ?></span></a>
<?php endif; ?>
                            </th>
                            <td class="stats-num"><?= $row['visitors'] === null ? '<span title="' . e(t('stats.visitors_not_split')) . '">—</span>' : e(number_format($row['visitors'])) ?></td>
                            <td class="stats-num"><?= e(number_format($row['views'])) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
<?php endif; ?>
