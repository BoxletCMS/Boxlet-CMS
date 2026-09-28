<?php

use App\Support\Url;

/**
 * The full activity log (PLAN.md D-052). Provided by AdminView::render().
 *
 * @var string $title
 * @var list<array{id: int, at: string, kind: string, action: string, subjectId: int|null, subject: string}> $rows
 * @var string $zone
 * @var int $page
 * @var int $pages
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="page-subtitle"><?= e(t('activity.intro')) ?></p>
<?php require __DIR__ . '/activity-rows.php'; ?>
<?php if ($pages > 1): ?>
        <nav class="activity-pager" aria-label="<?= e(t('activity.title')) ?>">
<?php if ($page > 1): ?>
            <a class="button button-secondary" href="<?= e(Url::admin('activity') . '?page=' . ($page - 1)) ?>"><?= e(t('activity.newer')) ?></a>
<?php endif; ?>
            <span class="activity-page"><?= e($page . ' / ' . $pages) ?></span>
<?php if ($page < $pages): ?>
            <a class="button button-secondary" href="<?= e(Url::admin('activity') . '?page=' . ($page + 1)) ?>"><?= e(t('activity.older')) ?></a>
<?php endif; ?>
        </nav>
<?php endif; ?>
