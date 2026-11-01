<?php

use App\Support\Url;

/**
 * AI (PLAN.md D-294): what were three sections in three places, one tab of three — translating
 * the pages through Anthropic (D-248), AI access over MCP (D-287), and llms.txt for AI search
 * (D-151). Each its own address. Required by settings.php.
 *
 * @var string $aiTab translation, access or search
 */
?>
        <nav class="tabs" aria-label="<?= e(t('settings.ai.tabs')) ?>">
<?php foreach (['translation' => [Url::admin('settings', 'ai'), t('settings.ai.translation')], 'access' => [Url::admin('settings', 'ai', 'access'), t('settings.ai.access')], 'search' => [Url::admin('settings', 'ai', 'search'), t('settings.ai.search')]] as $name => [$href, $label]): ?>
            <a href="<?= e($href) ?>"<?= $name === $aiTab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
        </nav>
<?php
require match ($aiTab) {
    'access' => dirname(__DIR__, 3) . '/Mcp/views/panel.php',
    'search' => dirname(__DIR__) . '/llms-panel.php',
    default => dirname(__DIR__, 3) . '/Ai/views/panel.php',
};
