<?php

use App\Support\Url;

/**
 * STATISTICS' TWO TABS (PLAN.md D-294): the counts, and the settings that govern them, which
 * stood at the foot of the one long Settings page. Each its own address. Required by
 * statistics.php and settings.php, in their scope.
 *
 * @var string $statsTab 'overview' or 'settings'
 */
?>
        <nav class="tabs" aria-label="<?= e(t('stats.tabs')) ?>">
<?php foreach (['overview' => [Url::admin('statistics'), t('stats.tab.overview')], 'settings' => [Url::admin('statistics', 'settings'), t('stats.tab.settings')]] as $name => [$href, $label]): ?>
            <a href="<?= e($href) ?>"<?= $name === $statsTab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
        </nav>
