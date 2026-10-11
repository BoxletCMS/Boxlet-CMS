<?php

/**
 * The inspector with nothing selected (README 4.5, the mockup): what the page is, and four
 * lines on how this screen works, and a translation's standing against its source.
 * builder-inspector.js puts it back when a selection ends.
 *
 * @var array{stale: array<int, mixed>, missing: int, sourceLabel: string} $translation
 */
?>
            <div class="inspector-nothing" data-pb-nothing>
                <div class="inspector-head">
                    <span class="inspector-icon" aria-hidden="true"><?= icon('file') ?></span>
                    <div class="inspector-titles">
                        <h2 class="inspector-name"><?= e(t('builder.nothing.title')) ?></h2>
                        <p class="inspector-context"><?= e(t('builder.nothing.context')) ?></p>
                    </div>
                </div>
<?php if ($translation['stale'] !== [] || $translation['missing'] > 0): ?>
                <?php /* A translation behind its source says so before anything else (D-043,
                         step 3); each stale block also carries its own mark and notice. */ ?>
                <div class="notice notice-warning inspector-notice" role="status">
<?php if ($translation['stale'] !== []): ?>
                    <p><?= e(t('translations.stale_summary', ['count' => (string) count($translation['stale']), 'language' => $translation['sourceLabel']])) ?></p>
<?php endif; ?>
<?php if ($translation['missing'] > 0): ?>
                    <p><?= e(t('translations.missing', ['count' => (string) $translation['missing'], 'language' => $translation['sourceLabel']])) ?></p>
<?php endif; ?>
                </div>
<?php endif; ?>
                <p class="inspector-lead"><?= e(t('builder.nothing.lead')) ?></p>
                <ul class="inspector-howto">
                    <li><?= icon('text-cursor') ?><span><?= e(t('builder.nothing.words')) ?></span></li>
                    <li><?= icon('mouse-pointer-click') ?><span><?= e(t('builder.nothing.block')) ?></span></li>
                    <li><?= icon('circle-plus') ?><span><?= e(t('builder.nothing.plus')) ?></span></li>
                    <li><?= icon('eye') ?><span><?= e(t('builder.nothing.publish')) ?></span></li>
                </ul>
            </div>
