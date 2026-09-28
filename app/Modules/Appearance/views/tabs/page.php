<?php

/**
 * The page tab of the Appearance screen (PLAN.md D-059). Included by appearance.php, whose
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
 * @var callable(string, float, float, float): string $slider
 * @var callable(string): string $ownColour
 * @var array<string, string> $colors
 */
?>
            <?php /* Its own group rather than more controls under Shape (D-031). Shape is
                     about the content — its spacing, corners, shadows and measure; these
                     are about the page as a whole. The header's width and the two bleeds
                     moved to the Header and Footer tabs (D-111): they stay design decisions,
                     but a person setting up the header looks for them there. */ ?>
            <fieldset class="fieldset">
                <p class="hint"><?= e(t('design.page_hint')) ?></p>
                <?= $segmented('boxed', $labels('boxed', App\Modules\Design\Tokens::BOXED)) ?>
                <?php /* EVERYTHING BELOW ONLY EXISTS ON A BOXED PAGE, AND IS ONLY SHOWN THERE
                         (PLAN.md D-122). D-067 kept these on screen with a hint saying so, on the
                         argument that a control that disappears is worse than one that explains
                         itself; the owner then set a sheet's width for a page that was not
                         boxed, saw nothing happen, and took the page to be boxed. A hint read
                         only when hints are on does not stop that. Hidden by the stylesheet on
                         the form's own state, so it follows the choice at once; the fields
                         stay in the form and are posted either way. */ ?>
                <div class="when-boxed" data-when-boxed>
                <?= $segmented('page_background', $labels('page_background', App\Modules\Design\Tokens::PAGE_BACKGROUND)) ?>
                <p class="hint"><?= e(t('design.page_background_hint')) ?></p>
                <?php /* Or a colour of its own (D-076). The one place on the page where a
                         free colour carries no risk at all: it shows only around a BOXED
                         page, so no text ever lands on it and the gauge gains no pair. */ ?>
                <?= $ownColour('page_background_colour') ?>

                <?php /* THE SHEET AND ITS EDGES (D-067), shown only on a boxed page since D-122. */ ?>
                <?php /* A BOX OF A WIDTH (D-116): the sheet's own width, and the room above
                         and below it — zero glues a header or footer that breaks out of the
                         sheet to it. The margin at the sides stays the frame's. */ ?>
                <?= $slider('sheet_width', App\Modules\Design\Tokens::SHEET_WIDTH_MIN, App\Modules\Design\Tokens::SHEET_WIDTH_MAX, App\Modules\Design\Tokens::SHEET_WIDTH_STEP) ?>
                <?= $segmented('frame', $labels('frame', array_keys(App\Modules\Design\Tokens::FRAME))) ?>
                <?= $slider('sheet_gap', 0, App\Modules\Design\Tokens::SHEET_GAP_MAX, 1) ?>
                <?= $segmented('sheet_radius', $labels('sheet_radius', App\Modules\Design\Tokens::SHEET_RADIUS)) ?>
                <?= $segmented('sheet_shadow', $labels('sheet_shadow', App\Modules\Design\Tokens::SHEET_SHADOW)) ?>
                </div>
            </fieldset>
