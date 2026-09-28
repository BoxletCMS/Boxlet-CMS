<?php

use App\Modules\Design\Tokens;

/**
 * The shape tab of the Appearance screen (PLAN.md D-059). Included by appearance.php, whose
 * variables and helpers it reads: $decisions, $errors, $colors, $pairs, $readable, and the
 * $segmented, $labels, $error and $swatch closures.
 *
 * NO LEGEND REPEATING THE TAB'S OWN NAME: the strip above already says which of the five
 * this is, and a heading that restates it is furniture.
 *
 * @var array<string, string> $decisions
 * @var array<string, string> $errors
 * @var callable(string): string $error one field's message, or an empty slot for the script
 * @var callable(string, array<array-key, string>, string=, string=): string $segmented
 * @var callable(string, float, float, float): string $slider
 * @var callable(string, list<string>): array<string, string> $labels
 * @var array{space: int, section: int, radius: int, container: int} $readable
 */
?>
            <fieldset class="fieldset">
                <?= $segmented('spacing', $labels('spacing', array_keys(Tokens::SPACING))) ?>
                <?= $segmented('radius', $labels('radius', Tokens::RADIUS)) ?>
                <?= $segmented('shadow', $labels('shadow', Tokens::SHADOW)) ?>

                <?php /* THE ONE DECISION THAT IS A NUMBER (D-062). Four names could not answer
                         "a little narrower than this", and the measure is the decision that
                         most changes whether a page is comfortable to read. Both units,
                         because rem is the design's own and pixels are what a person can
                         picture. */ ?>
                <?= $slider('container', Tokens::CONTAINER_MIN, Tokens::CONTAINER_MAX, Tokens::CONTAINER_STEP) ?>

                <?php /* The spacing scale as it actually runs: seven steps, each a multiple
                         of the one decision above it. A ramp says "evenly" in a way a list of
                         numbers does not. */ ?>
                <div class="field">
                    <span class="field-label"><?= e(t('design.space_ramp')) ?></span>
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
            </fieldset>
