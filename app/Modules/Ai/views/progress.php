<?php

use App\Support\Url;

/**
 * Where an AI translation runs (PLAN.md D-248). Provided by AdminView::render().
 *
 * @var array{done: int, failed: int, pending: int, total: int, pages: list<array{id: int, title: string, locale: string, pending: int, failed: int}>, messages: list<string>} $progress
 * @var bool $ready whether a key is set
 * @var int|null $back the page to go back to, when one page was translated
 * @var string $csrf
 */
$finished = $progress['done'] + $progress['failed'];
$running = $progress['pending'] > 0 && $ready;
// Parts go in the order they were queued, so the first page with any waiting is the one being
// translated now (D-259); ai-progress.js says the same as each answer comes.
$working = null;
foreach ($progress['pages'] as $page) {
    if ($running && $page['pending'] > 0) {
        $working = $page['id'];
        break;
    }
}
$state = static fn (int $id, int $pending, int $failed): string => match (true) {
    $id === $working => t('ai.progress.page_working'),
    $pending > 0 => t('ai.progress.page_waiting'),
    $failed > 0 => t('ai.progress.page_failed'),
    default => t('ai.progress.page_done'),
};
?>
        <div class="page-header">
            <h1><?= e(t('ai.progress.title')) ?></h1>
        </div>
        <p class="page-subtitle"><?= e(t('ai.progress.intro')) ?></p>
<?php if (!$ready): ?>
        <p class="notice notice-warning"><?= e(t('ai.progress.not_ready')) ?> <a href="<?= e(Url::admin('settings') . '#ai') ?>"><?= e(t('ai.progress.to_settings')) ?></a></p>
<?php endif; ?>
<?php if ($progress['total'] === 0): ?>
        <p><?= e(t('ai.progress.nothing')) ?></p>
<?php else: ?>
        <section class="panel stack ai-progress" data-ai<?= $running ? ' data-ai-run' : '' ?>
                 data-ai-step="<?= e(Url::admin('translate', 'step')) ?>" data-ai-csrf="<?= e($csrf) ?>"
                 data-ai-words="<?= e((string) json_encode(['lost' => t('ai.progress.lost'), 'retrying' => t('ai.progress.retrying'), 'of' => t('ai.progress.of'), 'working' => t('ai.progress.page_working'), 'waiting' => t('ai.progress.page_waiting'), 'failed' => t('ai.progress.page_failed'), 'done' => t('ai.progress.page_done')], JSON_UNESCAPED_UNICODE)) ?>">
            <progress data-ai-bar max="<?= e((string) $progress['total']) ?>" value="<?= e((string) $finished) ?>"></progress>
            <p class="ai-progress-count" data-ai-count role="status"><?= e(t('ai.progress.of', ['done' => (string) $finished, 'total' => (string) $progress['total']])) ?></p>
            <p class="notice notice-error" data-ai-stopped role="alert" hidden></p>
            <ul class="ai-progress-pages">
<?php foreach ($progress['pages'] as $page): ?>
                <li data-ai-page="<?= e((string) $page['id']) ?>">
                    <a href="<?= e(Url::admin('pages', $page['id'])) ?>"><?= e($page['title']) ?></a>
                    <span class="ai-progress-lang"><?= e(strtoupper($page['locale'])) ?></span>
                    <span class="ai-progress-state" data-ai-page-state><?= e($state($page['id'], $page['pending'], $page['failed'])) ?></span>
                </li>
<?php endforeach; ?>
            </ul>
<?php if ($progress['messages'] !== []): ?>
            <div class="notice notice-warning">
                <p><?= e(t('ai.progress.some_failed')) ?></p>
                <ul>
<?php foreach ($progress['messages'] as $message): ?>
                    <li><?= e($message) ?></li>
<?php endforeach; ?>
                </ul>
            </div>
<?php endif; ?>
            <div class="form-actions" data-ai-actions>
<?php if ($progress['pending'] > 0): ?>
                <?php /* With the script, the parts go one after another by themselves: the
                         button is hidden and this says so; it comes back if the run stops
                         (D-259). Without it, the button does one part per press. */ ?>
                <p class="ai-progress-running" data-ai-running hidden><?= e(t('ai.progress.running')) ?></p>
                <form method="post" action="<?= e(Url::admin('translate', 'step')) ?>" data-ai-next>
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="button"<?= $ready ? '' : ' disabled' ?>><?= e(t('ai.progress.next')) ?></button>
                </form>
                <form method="post" action="<?= e(Url::admin('translate', 'stop')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="button button-secondary" data-confirm="<?= e(t('ai.progress.stop_confirm')) ?>"><?= e(t('ai.progress.stop')) ?></button>
                </form>
<?php else: ?>
                <p class="ai-progress-done"><?= e(t('ai.progress.done')) ?></p>
<?php if ($progress['failed'] > 0): ?>
                <form method="post" action="<?= e(Url::admin('translate', 'retry')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="button button-secondary"><?= e(t('ai.progress.retry')) ?></button>
                </form>
<?php endif; ?>
                <a class="button" href="<?= e($back !== null ? Url::admin('pages', $back) : Url::admin('pages')) ?>"><?= e($back !== null ? t('ai.progress.open_page') : t('ai.progress.to_pages')) ?></a>
<?php endif; ?>
            </div>
        </section>
<?php endif; ?>
