<?php

use App\Modules\Design\Tokens;
use App\Support\Controls;

/**
 * Typography (PLAN.md D-157, D-185): the two families and the pairings, the sizes, the
 * headings, and the three nudges folded away. Included by appearance.php inside its section, with the closures of
 * parts/controls.php in scope.
 *
 * @var array<string, string> $decisions
 * @var array<string, string> $readouts
 * @var Closure(string): string $control
 * @var Closure(string, string, array<string, mixed>=): array<string, mixed> $rowOptions
 * @var Closure(string, string, string, bool=): string $group
 * @var Closure(string): string $fontPicker
 * @var Closure(): string $pairingTiles
 */

ob_start();
?>
                    <?php /* The two families, each from the library (D-185), and the pairings as
                             shortcuts that set both. */ ?>
                    <?= $fontPicker('heading_font') ?>
                    <?= $fontPicker('body_font') ?>
                    <?= Controls::row(t('design.pairings'), $pairingTiles(), ['hint' => field_hint('hint.design.pairings'), 'error' => '']) ?>
<?php $typeface = (string) ob_get_clean(); ob_start(); ?>
                    <?php /* SIZE FIRST, THEN SCALE: how big the text is, then how much bigger each
                             heading is than the one under it (D-062, D-066). */ ?>
                    <?= $control('text_size') ?>
                    <?= $control('scale') ?>
                    <?= $control('line_height') ?>
                    <?php /* THE SPECIMEN, DRAWN RATHER THAN DESCRIBED (D-065, D-075): the lines at
                             the sizes the page will really use, shrunk together to fit, in the
                             pairing being chosen. The numbers beside them are the server's. */ ?>
                    <div class="specimen" data-heading-font="<?= e($decisions['heading_font']) ?>" data-body-font="<?= e($decisions['body_font']) ?>" aria-hidden="true">
<?php foreach ([['4xl', 'design.specimen.hero', 'heading'], ['2xl', 'design.specimen.text_heading', 'heading'], ['base', 'design.specimen.text_body', 'body'], ['sm', 'design.specimen.small', 'body']] as [$step, $key, $half]): ?>
                        <p class="specimen-line specimen-<?= e($step) ?> specimen-<?= e($half) ?>" data-specimen="<?= e($step) ?>">
                            <span><?= e(t($key)) ?></span><em data-readout="specimen.<?= e($step) ?>" data-specimen-size="<?= e($step) ?>"><?= e($readouts['specimen.' . $step] ?? '') ?></em>
                        </p>
<?php endforeach; ?>
                    </div>
                    <p class="derived" data-readout="phone"><?= e($readouts['phone'] ?? '') ?></p>
<?php $sizes = (string) ob_get_clean(); ob_start(); ?>
                    <?php /* The pairing's treatment until the owner moves one: each slider stands
                             where the typeface puts it, and a reset gives it back (D-164). */ ?>
                    <?= $control('heading_weight') ?>
                    <?= $control('tracking') ?>
                    <?= $control('caps') ?>
<?php $headings = (string) ob_get_clean(); ob_start(); ?>
                    <?php /* The three exceptions to the scale (D-066): a ratio cannot say "that
                             headline, two pixels smaller". */ ?>
<?php foreach (array_keys(Tokens::NUDGES) as $key): ?>
                    <?= $control($key) ?>
<?php endforeach; ?>
<?php $fine = (string) ob_get_clean(); ?>
                <?= $group('typography', 'typeface', $typeface) ?>
                <?= $group('typography', 'sizes', $sizes) ?>
                <?= $group('typography', 'headings', $headings) ?>
                <?= $group('typography', 'fine', $fine, false) ?>
