<?php

/**
 * Space & shape (PLAN.md D-157): the spacing every margin is a multiple of, the corners, the
 * shadows. Included by appearance.php inside its section, with the closures of
 * parts/controls.php in scope. The content's WIDTH moved to Layout & widths, with every
 * other width.
 *
 * @var array{space: int, section: int, radius: int, container: int} $readable
 * @var Closure(string): string $control
 * @var Closure(string, string, string, bool=): string $group
 */
ob_start();
?>
                    <?= $control('spacing') ?>
                    <?= $control('section_gap') ?>
                    <?php /* The spacing scale as it actually runs: seven steps, each a multiple of
                             the one decision above it. A ramp says "evenly" in a way a list of
                             numbers does not. */ ?>
                    <div class="ramp-field">
                        <div class="ramp" aria-hidden="true">
<?php foreach ([0.25, 0.5, 1, 2, 4, 6, 8] as $factor): ?>
                            <span class="ramp-step" data-ramp="<?= e((string) $factor) ?>"></span>
<?php endforeach; ?>
                        </div>
                        <span class="hint"><?= e(t('design.readable.space', [
                            'space' => $readable['space'] . 'px',
                            'section' => $readable['section'] . 'px',
                        ])) ?></span>
                    </div>
<?php $spacing = (string) ob_get_clean(); ?>
                <?= $group('space', 'spacing', $spacing) ?>
                <?= $group('space', 'shape', $control('radius') . $control('border_width')) ?>
                <?= $group('space', 'shadow', $control('shadow') . $control('shadow_strength')) ?>
