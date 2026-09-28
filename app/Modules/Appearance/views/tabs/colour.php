<?php

use App\Modules\Design\Palette;
use App\Modules\Design\Tokens;

/**
 * The colour tab of the Appearance screen (PLAN.md D-059). Included by appearance.php, whose
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
 * @var callable(string, list<string>): array<string, string> $labels
 * @var array<string, string> $colors
 * @var list<array{pair: string, decision: string, ratio: float, required: float, passes: bool, foreground: string, background: string}> $pairs
 * @var callable(string, string): string $swatch
 */
/**
 * One row of the contrast gauge (D-058): the pair as it would actually look, then its name,
 * then what it measures. The specimen is an SVG rather than a styled box, because the admin's
 * policy forbids a style attribute and its stylesheet may not hold a site colour.
 *
 * @param array{pair: string, ratio: float, passes: bool, foreground: string, background: string} $pair
 */
$gaugeRow = static function (array $pair): string {
    $ratio = number_format($pair['ratio'], 2);

    return '<li class="gauge-row' . ($pair['passes'] ? '' : ' gauge-fails') . '" data-pair="' . e($pair['pair']) . '">'
        // 28x18, not 40x24: twelve of these in a 312px column, and the sample only has to
        // show a pair of colours against each other (D-075).
        . '<svg class="gauge-sample" viewBox="0 0 28 18" aria-hidden="true">'
        . '<rect width="28" height="18" fill="' . e($pair['background']) . '" data-pair-background/>'
        . '<text x="14" y="13" text-anchor="middle" font-size="11" fill="' . e($pair['foreground']) . '" data-pair-foreground>' . e(t('design.contrast.sample')) . '</text>'
        . '</svg>'
        . '<span class="gauge-name">' . e(t('design.pair.' . $pair['pair'])) . '</span>'
        . '<span class="gauge-ratio"><span data-pair-ratio>' . e($ratio) . '</span>:1'
        . ' <span class="gauge-verdict" data-pair-verdict data-pass="' . e(t('design.contrast.pass')) . '" data-fail="' . e(t('design.contrast.fail')) . '">'
        . e(t($pair['passes'] ? 'design.contrast.pass' : 'design.contrast.fail')) . '</span></span>'
        . '</li>';
};
/* The six that matter most stand open; the rest fold away — but a pair that FAILS is never
   folded, or the screen would hide the one thing the owner has to act on. */
$openPairs = [];
$foldedPairs = [];
foreach ($pairs as $index => $pair) {
    if ($index < 6 || !$pair['passes']) {
        $openPairs[] = $pair;
        continue;
    }
    $foldedPairs[] = $pair;
}
?>
            <fieldset class="fieldset">
                <div class="field">
                    <label for="design-seed"><?= e(t('design.seed')) ?></label>
                    <div class="colour-field">
                        <input type="color" class="colour-input" id="design-seed" name="seed" value="<?= e($decisions['seed']) ?>">
                        <output class="colour-value" for="design-seed" data-colour-for="design-seed"><?= e($decisions['seed']) ?></output>
                    </div>
                    <span class="hint"><?= e(t('design.seed_hint')) ?></span>
                    <?= $error('seed') ?>
                </div>
                <div class="field">
                    <label class="checkbox"><input type="checkbox" name="use_secondary" value="1"<?= $decisions['secondary'] !== '' ? ' checked' : '' ?>> <span><?= e(t('design.use_secondary')) ?></span></label>
                    <div class="colour-field">
                        <input type="color" class="colour-input" id="design-secondary" name="secondary" value="<?= e($decisions['secondary'] !== '' ? $decisions['secondary'] : $colors['contrast']) ?>" aria-label="<?= e(t('design.secondary')) ?>">
                        <output class="colour-value" for="design-secondary" data-colour-for="design-secondary"><?= e($decisions['secondary'] !== '' ? $decisions['secondary'] : $colors['contrast']) ?></output>
                    </div>
                    <?= field_hint('hint.design.secondary') ?>
                    <?= $error('secondary') ?>
                </div>
                <?= $segmented('surface_contrast', $labels('surface_contrast', Tokens::SURFACE_CONTRAST)) ?>

                <?php /* ONE LIST OF ROLES (D-074).
                         It was two: fifteen derived colours that could not be touched, and a
                         folded panel holding the seven that could. The same information in two
                         places, and the half that can be CHANGED was the half that was closed.
                         A palette is one thing, so it is one list — the seven that can be the
                         owner's carry a colour input, the rest say they are worked out. */ ?>
                <div class="field">
                    <div class="field-row">
                        <span class="field-label" id="design-palette-label"><?= e(t('design.palette')) ?></span>
                        <?php /* Shown only when there is something to undo — a button that
                                 would do nothing teaches people not to trust buttons. Which
                                 is a CSS question, not a render-time one: the switches flip
                                 under the owner's hand as colours are picked (D-065), and a
                                 button the server decided about would be a round trip behind
                                 them. */ ?>
                        <button type="submit" form="design-form" name="action" value="colour:free" class="button button-quiet palette-reset"><?= e(t('design.by_hand.free_all')) ?></button>
                    </div>
                    <span class="hint"><?= e(t('design.palette_hint')) ?></span>
                    <ul class="roles" role="list" aria-labelledby="design-palette-label">
<?php foreach ($colors as $name => $hex): ?>
<?php
    $mine = in_array($name, Palette::BY_HAND, true);
    $field = 'color_' . $name;
    $id = 'design-' . $field;
    $taken = $mine && $decisions[$field] !== '';
?>
                        <li class="role">
<?php if ($mine): ?>
                            <?php /* THE SWATCH IS THE CONTROL. A colour beside a button that
                                     opens a colour picker is two things where there is one:
                                     the square IS the picker. */ ?>
                            <input type="color" class="role-swatch" id="<?= e($id) ?>" name="<?= e($field) ?>"
                                   value="<?= e($taken ? $decisions[$field] : $hex) ?>"
                                   data-by-hand="<?= e($field) ?>"
                                   aria-label="<?= e(t('design.color.' . $name)) ?>">
                            <span class="role-name" aria-hidden="true" title="<?= e(t('design.color.' . $name)) ?>"><?= e(t('design.color.' . $name)) ?></span>
                            <?php /* BOTH HANDLES, and they never disagree. data-colour-for
                                     follows the HAND, so the hex keeps up with the picker
                                     while it is being dragged; data-swatch-value is what the
                                     server's answer is written into, which is the only
                                     evidence on the screen that the palette was worked out
                                     again. For a role the owner has taken they are the same
                                     colour, and for one they have not, the answer sets the
                                     input the other reads. */ ?>
                            <code class="role-value" data-colour-for="<?= e($id) ?>" data-swatch-value="<?= e($name) ?>"><?= e($taken ? $decisions[$field] : $hex) ?></code>
                            <?php /* The switch is what makes the colour the owner's, and it is
                                     MECHANISM: a colour input always carries some colour, so
                                     "is this mine" cannot be read off its value (D-065).
                                     Clipped, never faded — opacity is what the admin's contrast
                                     rule forbids for making a control quiet (D-012). */ ?>
                            <input type="checkbox" name="<?= e($field) ?>_on" value="1"<?= $taken ? ' checked' : '' ?> data-by-hand-switch="<?= e($field) ?>" tabindex="-1" aria-hidden="true">
                            <button type="submit" form="design-form" name="action" value="colour:free:<?= e($name) ?>" class="icon-button role-free"
                                    title="<?= e(t('design.by_hand.free', ['role' => t('design.color.' . $name)])) ?>">
                                <?= icon('history') ?><span class="visually-hidden"><?= e(t('design.by_hand.free', ['role' => t('design.color.' . $name)])) ?></span>
                            </button>
<?php else: ?>
                            <?= $swatch($hex, $name) ?>
                            <span class="role-name" title="<?= e(t('design.color.' . $name)) ?>"><?= e(t('design.color.' . $name)) ?></span>
                            <code class="role-value" data-swatch-value="<?= e($name) ?>"><?= e($hex) ?></code>
                            <span class="role-derived"><?= e(t('design.by_hand.computed')) ?></span>
<?php endif; ?>
                            <?= $error($field) ?>
                        </li>
<?php endforeach; ?>
                    </ul>
                </div>

                <?php /* THE GAUGE (D-058). Until now the screen could only say that a pair
                         FAILED, which made a palette passing by a hundredth look exactly like
                         one passing easily — and nobody can aim at a number they are never
                         shown. Every pair is measured on the server and listed here; Save
                         still refuses the same ones it always did. */ ?>
                <div class="field gauge" data-gauge>
                    <span class="field-label"><?= e(t('design.contrast.title')) ?></span>
                    <span class="hint"><?= e(t('design.contrast.intro')) ?></span>
                    <ul class="gauge-list" data-gauge-open>
<?php foreach ($openPairs as $pair): ?>
                        <?= $gaugeRow($pair) ?>
<?php endforeach; ?>
                    </ul>
<?php if ($foldedPairs !== []): ?>
                    <details class="gauge-more">
                        <summary><?= e(t('design.contrast.more', ['count' => count($foldedPairs)])) ?></summary>
                        <ul class="gauge-list" data-gauge-folded>
<?php foreach ($foldedPairs as $pair): ?>
                            <?= $gaugeRow($pair) ?>
<?php endforeach; ?>
                        </ul>
                    </details>
<?php endif; ?>
                </div>
            </fieldset>
