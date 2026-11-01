<?php

/**
 * Statistics' Settings tab (PLAN.md D-294): the panel that was the last section of Settings.
 * Provided by StatsSettingsController::show(); panel.php says what it needs.
 *
 * @var string $title
 * @var string $statsTab
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
<?php require __DIR__ . '/tabs.php'; ?>
        <div class="settings-sections stats-settings">
<?php require __DIR__ . '/panel.php'; ?>
        </div>
