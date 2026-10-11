<?php

use App\Support\Url;

/**
 * The builder's bar (README 4.1): back, the page's title, what it is, how its draft is saved,
 * the device, the language, undo and redo, the draft in a new tab, Discard and Publish. Labels
 * never wrap; the save state gives way first.
 *
 * @var array<string, mixed> $page
 * @var array<string, mixed> $document
 * @var string $state
 * @var int $pageId
 */
?>
    <header class="pb-bar">
        <a class="pb-icon-button" href="<?= e(Url::admin('pages')) ?>" title="<?= e(t('builder.back')) ?>"><?= icon('chevron-left') ?><span class="visually-hidden"><?= e(t('builder.back')) ?></span></a>
        <h1 class="pb-title" data-pb-title><?= e((string) ($document['title'] ?? '') !== '' ? (string) $document['title'] : t('pages.new')) ?></h1>
        <span class="pb-state status-<?= e($state) ?>" data-pb-state><?= e(t('pages.state.' . $state)) ?></span>
        <span class="pb-save" data-pb-save role="status"><?= icon('cloud-upload') ?><span data-pb-save-text><?= e(t('builder.save.idle')) ?></span></span>
        <div class="pb-devices" role="group" aria-label="<?= e(t('pages.device.label')) ?>">
<?php foreach (['desktop' => 'monitor', 'tablet' => 'tablet', 'phone' => 'smartphone'] as $device => $deviceIcon): ?>
            <button type="button" class="pb-device" data-device="<?= e($device) ?>" aria-pressed="<?= $device === 'desktop' ? 'true' : 'false' ?>" title="<?= e(t('builder.device.' . $device)) ?>"><?= icon($deviceIcon) ?><span class="visually-hidden"><?= e(t('builder.device.' . $device)) ?></span></button>
<?php endforeach; ?>
        </div>
<?php require __DIR__ . '/../languages-menu.php'; ?>
        <div class="pb-bar-end">
            <button type="button" class="pb-icon-button" data-pb-undo disabled title="<?= e(t('builder.undo')) ?>"><?= icon('undo-2') ?><span class="visually-hidden"><?= e(t('builder.undo')) ?></span></button>
            <button type="button" class="pb-icon-button" data-pb-redo disabled title="<?= e(t('builder.redo')) ?>"><?= icon('redo-2') ?><span class="visually-hidden"><?= e(t('builder.redo')) ?></span></button>
            <a class="pb-icon-button" href="<?= e(Url::admin('pages', $pageId, 'preview')) ?>" target="_blank" rel="noopener" title="<?= e(t('builder.preview')) ?>"><?= icon('external-link') ?><span class="visually-hidden"><?= e(t('builder.preview')) ?></span></a>
            <noscript><a class="button button-ghost" href="<?= e(Url::admin('pages', $pageId, 'form')) ?>"><?= e(t('pages.editor.fallback')) ?></a></noscript>
            <button type="button" class="button button-ghost" data-pb-discard<?= $state === 'changes' ? '' : ' hidden' ?>><?= e(t('builder.discard')) ?></button>
            <button type="button" class="button" data-pb-publish><?= e(t('builder.publish')) ?></button>
        </div>
    </header>
