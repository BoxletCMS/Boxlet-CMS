<?php

use App\Modules\Stats\StatsFilter;
use App\Modules\Stats\StatsQuery;
use App\Modules\Stats\StatsView;
use App\Support\Url;

/**
 * What the screen is showing and how to change it (PLAN.md O-20): the periods, a range of
 * your own, and what the figures are narrowed to. Required by statistics.php and all.php;
 * $all keeps a full table open while the period or the narrowing changes.
 *
 * @var StatsFilter $filter
 * @var string|null $all
 */
/** @param array<string, string> $add */
$address = static fn (array $add = [], array $without = []): string => Url::admin('statistics') . '?'
    . http_build_query($filter->asQuery($add + ($all !== null ? ['all' => $all] : []), $without));
?>
        <?php /* THE THREE OF THEM ARE ONE CONTROL ROW: which period, a range of your own,
                 and the days that adds up to. Side by side wherever there is room, and in
                 rows of their own where there is not — they used to take three lines of a
                 screen whose point is the figures below them. */ ?>
        <div class="stats-controls">
        <nav class="stats-periods" aria-label="<?= e(t('stats.period')) ?>">
<?php foreach (array_keys(StatsQuery::PERIODS) as $choice): ?>
            <a href="<?= e($address(['period' => $choice], ['from', 'to'])) ?>"<?= $filter->period === $choice ? ' aria-current="page"' : '' ?>><?= e(t('stats.period.' . $choice)) ?></a>
<?php endforeach; ?>
        </nav>

        <?php /* A range of your own: two days, and the screen shows them. A plain GET form,
                 carrying whatever is already narrowed so choosing days keeps it. */ ?>
        <form class="stats-range" method="get" action="<?= e(Url::admin('statistics')) ?>">
            <input type="hidden" name="period" value="custom">
<?php foreach ($filter->asQuery($all !== null ? ['all' => $all] : [], ['period', 'from', 'to']) as $key => $value): ?>
            <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
<?php endforeach; ?>
            <label for="stats-from"><?= e(t('stats.from')) ?></label>
            <input type="date" id="stats-from" name="from" value="<?= e($filter->from) ?>" max="<?= e($filter->to) ?>">
            <label for="stats-to"><?= e(t('stats.to')) ?></label>
            <input type="date" id="stats-to" name="to" value="<?= e($filter->to) ?>">
            <button type="submit" class="button button-secondary"><?= e(t('stats.show')) ?></button>
        </form>

        <p class="stats-days"><?= e($filter->from === $filter->to
            ? StatsView::day($filter->from, true)
            : StatsView::day($filter->from, true) . ' – ' . StatsView::day($filter->to, true)) ?></p>
        </div>

<?php if ($filter->isNarrowed()): ?>
        <?php /* What the figures are narrowed to, each removable: the screen never narrows
                 without saying so, and never without a way back. */ ?>
        <div class="stats-chips">
            <span class="stats-chips-label"><?= e(t('stats.narrowed')) ?></span>
<?php foreach ($filter->narrowed as $dimension => $value): ?>
            <a class="stats-chip" href="<?= e($address([], [$dimension])) ?>">
                <span class="stats-chip-kind"><?= e(t('stats.column.' . StatsView::table($dimension))) ?></span>
                <span><?= e(StatsView::label(StatsView::table($dimension), $value)) ?></span>
                <?= icon('x') ?><span class="visually-hidden"><?= e(t('stats.remove_filter')) ?></span>
            </a>
<?php endforeach; ?>
            <a class="stats-chip-clear" href="<?= e(Url::admin('statistics') . '?' . http_build_query($filter->asQuery($all !== null ? ['all' => $all] : [], array_keys(StatsFilter::DIMENSIONS)))) ?>"><?= e(t('stats.clear_filters')) ?></a>
        </div>
<?php endif; ?>
