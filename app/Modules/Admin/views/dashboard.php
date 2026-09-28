<?php

use App\Support\Url;

/**
 * The Overview (PLAN.md D-052). Provided by AdminView::render().
 *
 * @var string $title
 * @var list<array{label: string, value: string, note: string, href: string, word: bool}> $metrics
 * @var list<array{id: int, at: string, kind: string, action: string, subjectId: int|null, subject: string}> $rows
 * @var string $zone
 * @var list<array{title: string, where: string, href: string}> $issues
 * @var list<array{path: string, views: int, share: float}> $mostRead empty while statistics are off
 * @var int|null $homeId the home page's id, null before there is one
 * @var bool $maintenance
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
            <div class="form-actions">
<?php if ($homeId !== null): ?>
                <a class="button button-secondary" href="<?= e(Url::admin('pages', $homeId)) ?>"><?= e(t('overview.edit_home')) ?></a>
<?php endif; ?>
                <a class="button" href="<?= e(Url::admin('pages', 'new')) ?>"><?= e(t('pages.new')) ?></a>
            </div>
        </div>
        <p class="page-subtitle"><?= e(t('overview.intro')) ?></p>
<?php if ($maintenance): ?>
        <p class="notice notice-warning"><?= e(t('admin.dashboard.maintenance')) ?>
            <a href="<?= e(Url::admin('settings')) ?>"><?= e(t('admin.dashboard.maintenance_link')) ?></a></p>
<?php endif; ?>

        <?php /* The figures: tiles on a hairline grid, each the way into the screen it counts,
                 each with the note that says what the number means. */ ?>
        <ul class="metrics" role="list">
<?php foreach ($metrics as $metric): ?>
            <li><a class="metric" href="<?= e($metric['href']) ?>">
                <span class="metric-label"><?= e($metric['label']) ?></span>
                <span class="metric-value<?= $metric['word'] ? ' metric-word' : '' ?>"><?= e($metric['value']) ?></span>
                <span class="metric-note"><?= e($metric['note']) ?></span>
            </a></li>
<?php endforeach; ?>
        </ul>

        <div class="overview-columns">
            <section aria-labelledby="recent-heading">
                <div class="overview-kicker">
                    <h2 id="recent-heading"><?= e(t('activity.recent')) ?></h2>
                    <a href="<?= e(Url::admin('activity')) ?>"><?= e(t('activity.full_log')) ?></a>
                </div>
<?php require __DIR__ . '/activity-rows.php'; ?>
            </section>

            <div class="overview-side">
                <section aria-labelledby="attention-heading">
                    <div class="overview-kicker">
                        <h2 id="attention-heading"><?= e(t('overview.attention')) ?></h2>
                    </div>
<?php if ($issues === []): ?>
                    <p class="overview-calm"><?= icon('check') ?> <?= e(t('overview.attention_none')) ?></p>
<?php else: ?>
                    <ul class="issues" role="list">
<?php foreach ($issues as $issue): ?>
                        <li><a class="issue" href="<?= e($issue['href']) ?>">
                            <?= icon('circle-alert') ?>
                            <span>
                                <span class="issue-title"><?= e($issue['title']) ?></span>
                                <span class="issue-where"><?= e($issue['where']) ?></span>
                            </span>
                        </a></li>
<?php endforeach; ?>
                    </ul>
<?php endif; ?>
                </section>

<?php if ($mostRead !== []): ?>
                <section aria-labelledby="read-heading">
                    <div class="overview-kicker">
                        <h2 id="read-heading"><?= e(t('overview.most_read')) ?></h2>
                        <a href="<?= e(Url::admin('statistics') . '?period=30d') ?>"><?= e(t('stats.card_link')) ?></a>
                    </div>
                    <ol class="most-read" role="list">
<?php foreach ($mostRead as $page): ?>
                        <li>
                            <span class="most-read-row">
                                <span class="most-read-path"><?= e($page['path']) ?></span>
                                <span class="most-read-views"><?= e(number_format($page['views'])) ?></span>
                            </span>
                            <?php /* The track: an SVG rect as long as the share, since a width in a
                                     style attribute is refused by the admin's CSP. */ ?>
                            <svg class="most-read-track" viewBox="0 0 100 1" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="most-read-ground" width="100" height="1"/><rect class="most-read-fill" width="<?= e(number_format($page['share'], 1, '.', '')) ?>" height="1"/></svg>
                        </li>
<?php endforeach; ?>
                    </ol>
                </section>
<?php endif; ?>
            </div>
        </div>
