<?php

use App\Modules\Design\Tokens;
use App\Modules\Design\Typography;

/**
 * The type tab of the Appearance screen (PLAN.md D-059, D-065). Included by appearance.php,
 * whose variables and helpers it reads.
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
 * @var array<string, string> $readouts what every control comes to, in words
 */
?>
            <fieldset class="fieldset">
                <?php /* THE PAIRING IS CARDS, NOT A LIST OF NAMES. "Modern" means nothing on
                         its own; "Modern · Inter" is a choice a person can make, and the
                         sample is set in the face itself — the ONE place the admin may show
                         the site's typefaces, because they are what is being chosen. */ ?>
                <div class="field">
                    <span class="field-label" id="design-typography-label"><?= e(t('design.typography')) ?></span>
                    <div class="typefaces" role="radiogroup" aria-labelledby="design-typography-label">
<?php foreach (Typography::PAIRINGS as $name => $pairing): ?>
<?php
    $heading = Typography::FAMILIES[$pairing['heading']]['name'];
    $body = Typography::FAMILIES[$pairing['body']]['name'];
?>
                        <label class="typeface">
                            <input type="radio" name="typography" value="<?= e($name) ?>"<?= $decisions['typography'] === $name ? ' checked' : '' ?>>
                            <span class="typeface-sample" data-typeface="<?= e($name) ?>" aria-hidden="true">Aa</span>
                            <span class="typeface-name"><?= e(t('design.typography.' . $name)) ?>
                                <span><?= e($heading === $body ? $heading : $heading . ' / ' . $body) ?></span></span>
                        </label>
<?php endforeach; ?>
                    </div>
                    <?= field_hint('hint.design.typography') ?>
                    <?= $error('typography') ?>
                </div>

                <?php /* SIZE FIRST, THEN SCALE. They are different questions and were one
                         control for too long: the scale is how much bigger each heading is
                         than the one under it, and this is how big the text itself is
                         (D-062). */ ?>
                <?= $segmented('text_size', $labels('text_size', array_keys(Tokens::TEXT_SIZE))) ?>
                <?php /* THE STEP BETWEEN SIZES IS A NUMBER (D-066). Six named ratios were six
                         answers to a question with a continuum behind it, and the gap between
                         two of them was a decision nobody could make. */ ?>
                <?= $slider('scale', Tokens::SCALE_MIN, Tokens::SCALE_MAX, 0.005) ?>

                <?php /* The three exceptions to the scale (D-066): a ratio cannot say "that
                         headline, two pixels smaller". The readout gives the nudge AND what
                         the step comes to, because the nudge alone says nothing. */ ?>
<?php foreach (Tokens::NUDGES as $key => $bounds): ?>
                <?= $slider($key, (float) $bounds['min'], (float) $bounds['max'], 1) ?>
<?php endforeach; ?>

                <?php /* The heading treatment the PAIRING gives, which the owner may take
                         over: '' follows the typeface, exactly as a chrome choice follows
                         the character (D-066). */ ?>
<?php
    // The weights are their own labels — "600" says more than any word for it would.
    /** @var array<array-key, string> $weights — PHP turns '600' into 600, and pretending
     *  otherwise is how a type stops being true. */
    $weights = ['' => t('design.heading_weight.follow')];
    foreach (Tokens::HEADING_WEIGHTS as $weight) {
        $weights[(string) $weight] = $weight;
    }
?>
                <?= $segmented('heading_weight', $weights, '', t('design.follows_pairing')) ?>
                <?= $segmented('tracking', ['' => t('design.tracking.follow')] + $labels('tracking', array_keys(Tokens::TRACKING)), '', t('design.follows_pairing')) ?>
                <?= $segmented('caps', ['' => t('design.caps.follow')] + $labels('caps', array_keys(Tokens::CAPS)), '', t('design.follows_pairing')) ?>

                <?php /* THE SPECIMEN, DRAWN RATHER THAN DESCRIBED (D-065, D-075).
                         It was fixed at 1.6rem, so the scale slider moved the number on the
                         right and the sample looked exactly the same — which took away its
                         only reason to exist, the RELATION between the sizes. The lines are
                         drawn at the sizes the page will really use, scaled down together
                         to fit the column, and in the pairing being chosen: the cards above
                         already take the site's faces, because they are what is being
                         chosen, and so is this.
                         THE NUMBERS STAY REAL. The pixels beside each line are the server's,
                         not the shrunken ones — they are what the site gets. */ ?>
                <div class="specimen" data-typeface="<?= e($decisions['typography']) ?>" aria-hidden="true">
<?php foreach ([['4xl', 'design.specimen.hero', 'heading'], ['2xl', 'design.specimen.text_heading', 'heading'], ['base', 'design.specimen.text_body', 'body'], ['sm', 'design.specimen.small', 'body']] as [$step, $key, $half]): ?>
                    <p class="specimen-line specimen-<?= e($step) ?> specimen-<?= e($half) ?>" data-specimen="<?= e($step) ?>">
                        <span><?= e(t($key)) ?></span><em data-readout="specimen.<?= e($step) ?>" data-specimen-size="<?= e($step) ?>"><?= e($readouts['specimen.' . $step] ?? '') ?></em>
                    </p>
<?php endforeach; ?>
                </div>
                <p class="derived" data-readout="phone"><?= e($readouts['phone'] ?? '') ?></p>
            </fieldset>
