<?php

use App\Modules\Appearance\LayoutDiagram;

/**
 * Layout & widths (PLAN.md D-157): ONE PLACE FOR EVERY WIDTH. The content's measure was under
 * Shape, the boxed page and its sheet under Page, how far the header and footer reach under
 * Header and Footer — five widths in four tabs, and the only way to see how they related was
 * to look at the result. Here they are together, under a drawing of all of them.
 *
 * Included by appearance.php inside its section, with the closures of parts/controls.php in
 * scope.
 *
 * @var array<string, string> $decisions
 * @var array<string, string> $readouts
 * @var Closure(string): string $control
 * @var Closure(string): string $ownColour
 * @var Closure(string, string, string, bool=): string $group
 */
ob_start();
?>
                    <?php /* Drawn by the server from the decisions, moved by the answer /check gives
                             while a control changes (LayoutDiagram). The legend is the picture's
                             accessible name. */ ?>
                    <figure class="layout-figure">
                        <?= LayoutDiagram::svg($decisions) ?>
                        <figcaption class="layout-legend" id="layout-diagram-legend">
                            <span class="legend-sheet" data-readout="layout.sheet"><?= e($readouts['layout.sheet'] ?? '') ?></span>
                            <span class="legend-content" data-readout="layout.content"><?= e($readouts['layout.content'] ?? '') ?></span>
                            <span class="legend-chrome"><?= e(t('inspector.layout.chrome')) ?></span>
                        </figcaption>
                    </figure>
<?php $diagram = (string) ob_get_clean(); ob_start(); ?>
                    <?php /* A NUMBER, NOT FOUR NAMES (D-062): the measure is the decision that most
                             changes whether a page is comfortable to read. The names stand under
                             the slider where their widths are. */ ?>
                    <?= $control('container') ?>
<?php $content = (string) ob_get_clean(); ob_start(); ?>
                    <?= $control('boxed') ?>
                    <?php /* EVERYTHING BELOW ONLY EXISTS ON A BOXED PAGE, AND IS ONLY SHOWN THERE
                             (D-122): an owner once set a sheet's width for a page that was not
                             boxed, saw nothing happen, and took the page to be boxed. Hidden by
                             the stylesheet on the form's own state; the fields stay in the form. */ ?>
                    <div class="when-boxed" data-when-boxed>
                        <?= $control('sheet_width') ?>
                        <?= $control('frame') ?>
                        <?= $control('sheet_gap') ?>
                        <?= $control('sheet_radius') ?>
                        <?= $control('sheet_shadow') ?>
                        <?= $control('page_background') ?>
                        <?php /* Or a colour of its own (D-076): it shows only around a boxed page, so
                                 no text ever lands on it and the check gains no pair. */ ?>
                        <?= $ownColour('page_background_colour') ?>
                    </div>
<?php $page = (string) ob_get_clean(); ob_start(); ?>
                    <?php /* TWO QUESTIONS PER PART, because the stored model has two (D-123): how far
                             the BAR reaches — inside the sheet or across the window, a question
                             only a boxed page asks — and what the logo and menu INSIDE it line up
                             with. An answer that would change nothing is not offered
                             (admin-appearance.css), and one already chosen is never hidden. */ ?>
                    <div class="when-boxed" data-when-boxed><?= $control('header_bleed') ?></div>
                    <?= $control('header_width') ?>
                    <div class="when-boxed" data-when-boxed><?= $control('footer_bleed') ?></div>
                    <?= $control('footer_width') ?>
<?php $chrome = (string) ob_get_clean(); ?>
                <?= $group('layout', 'diagram', $diagram) ?>
                <?= $group('layout', 'content', $content) ?>
                <?= $group('layout', 'page', $page) ?>
                <?= $group('layout', 'chrome', $chrome) ?>
