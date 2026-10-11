<?php

use App\Modules\Appearance\Overrides;
use App\Support\Url;

/**
 * The Appearance screen (PLAN.md D-059, D-157): a bar, the picture, and an inspector that
 * opens on a home — where to start from, how much is the owner's own, the six sections —
 * and goes into one section at a time.
 *
 * SECTIONS, NOT TABS. Six tabs held seventy controls in a 312px column, the five widths sat
 * in four of them, and the characters had a column of their own the picture needed. Now the
 * home lists the sections with a line saying what each holds, every width is in one of
 * them, and the characters are tiles at the top of the home.
 *
 * WITHOUT JAVASCRIPT IT IS ONE COLUMN: the home, then every section under it, each row of the
 * home's list a link down to its section, every group a <details> already open, every reset a
 * plain submit. appearance-sections.js turns that into views, one at a time, with the
 * browser's Back going from a section to the home. Nothing is only reachable with a script.
 *
 * @var array<string, string> $decisions
 * @var array<string, string> $errors keyed by the decision or the field at fault
 * @var list<string> $changed the keys the owner has made theirs over $basis (D-158)
 * @var string $basis the character the screen measures against
 * @var string $character character loaded into the form, '' when none
 * @var \App\Core\Blocks $chromeBlocks the header and footer blocks, whose layouts draw their arrangements (D-166)
 * @var array<string, string> $publishedFields what the published site's controls show, by field (D-181)
 * @var array<string, string> $keywords more words each control is found by, by key (D-181)
 * @var string $title
 * @var string $csrf
 */

require __DIR__ . '/parts/controls.php';
require __DIR__ . '/cards.php';
$icons = ['colours' => 'palette', 'typography' => 'type', 'space' => 'box', 'layout' => 'panels-top-left', 'header' => 'panel-top', 'footer' => 'panel-bottom', 'buttons' => 'square-play'];
?>
        <div class="appearance" data-appearance>
<?php require __DIR__ . '/parts/bar.php'; ?>
<?php require __DIR__ . '/parts/forms.php'; ?>

            <?php /* ONE FORM AROUND THE PICTURE AND THE CONTROLS. Every control, every tile and
                     every design in the library posts the same screen — that is what stopped a
                     character load from clearing the header (D-059) — so the form IS the layout. */ ?>
            <form id="design-form" method="post" action="<?= e(Url::admin('appearance')) ?>" class="appearance-body design-form" data-design-form
                  data-check-url="<?= e(Url::admin('appearance', 'check')) ?>" data-preview-url="<?= e(Url::admin('appearance', 'preview')) ?>"
                  data-stylesheet-url="<?= e(Url::admin('appearance', 'stylesheet')) ?>"
                  data-published-fields="<?= e((string) json_encode($publishedFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="character" value="<?= e($character) ?>">

<?php require __DIR__ . '/parts/stage.php'; ?>

                <div class="appearance-inspector" data-inspector data-hints-root="appearance">
<?php require __DIR__ . '/parts/panels.php'; ?>
                    <?php /* HINTS ON DEMAND (D-078): off until asked for, remembered in this
                             browser. Without a script the hints show and this is not there.
                             AN ICON IN THE INSPECTOR'S TOP ROW, not a row of its own over the
                             content (D-161): it stands in the corner the first row of every
                             view leaves free. Both words live in the markup, because they are
                             translated and the script is not; they go in its label and title. */ ?>
                    <button type="button" class="icon-button hints-icon" data-hints-toggle hidden
                            aria-pressed="false" title="<?= e(t('hints.show')) ?>"
                            data-show="<?= e(t('hints.show')) ?>"
                            data-hide="<?= e(t('hints.hide')) ?>"><?= icon('circle-help') ?><span class="visually-hidden" data-hints-label><?= e(t('hints.show')) ?></span></button>
                    <?php /* SEARCH (D-181): every control, by its name, its section's and its
                             group's, the words of its choices and a few more ("dark", "sticky").
                             With a script only: without one every control is already on the one
                             column, where the browser's own find reaches it. */ ?>
                    <div class="appearance-search" data-search hidden>
                        <label class="visually-hidden" for="appearance-search"><?= e(t('appearance.search')) ?></label>
                        <?= icon('search') ?>
                        <input type="search" id="appearance-search" form="appearance-quick" autocomplete="off"
                               placeholder="<?= e(t('appearance.search_placeholder')) ?>"
                               data-keywords="<?= e((string) json_encode($keywords, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">
                        <p class="appearance-search-none" data-search-none hidden><?= e(t('appearance.search_none')) ?></p>
                    </div>
<?php require __DIR__ . '/inspector/home.php'; ?>
<?php foreach (array_keys(Overrides::SECTIONS) as $section): ?>
<?php $count = Overrides::count($changed, $section); ?>
                    <section class="inspector-view inspector-section<?= $count > 0 ? ' has-changes' : '' ?>" id="section-<?= e($section) ?>" data-view="<?= e($section) ?>" aria-labelledby="section-<?= e($section) ?>-title">
                        <div class="section-head">
                            <?php /* Back to the home: a link, so it is one without a script too
                                     — up the page to where the list is. */ ?>
                            <a class="section-back" href="#appearance-home" data-back><?= icon('arrow-left') ?> <?= e(t('inspector.back')) ?></a>
                            <?php /* The title and its reset on ONE line (D-161), the reset small
                                     and at the end: shown only while the section holds a change
                                     (the class above, kept live by appearance-overrides.js), and
                                     taking no room of its own when it is not. */ ?>
                            <div class="section-title-row">
                                <h2 class="section-title" id="section-<?= e($section) ?>-title"><?= icon($icons[$section]) ?> <?= e(t('inspector.section.' . $section)) ?></h2>
                                <button type="submit" form="design-form" name="action" value="reset:section:<?= e($section) ?>" class="button button-quiet section-reset"><?= icon('history') ?> <?= e(t('inspector.reset.section')) ?></button>
                            </div>
                        </div>
<?php include __DIR__ . '/sections/' . $section . '.php'; ?>
                    </section>
<?php endforeach; ?>
                </div>
            </form>
        </div>

        <?php /* The faces the typeface cards are set in, loaded by this screen alone. */ ?>
        <link rel="stylesheet" href="<?= e(Url::admin('appearance', 'typefaces')) ?>">

        <?php /* Without JavaScript this form sends the current values to the preview frame. */ ?>
        <form id="design-preview-form" method="get" action="<?= e(Url::admin('appearance', 'preview')) ?>" target="design-preview" class="visually-hidden"></form>
        <script src="<?= e(Url::versioned('assets/appearance.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-readouts.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-fonts.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-overrides.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-quick.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-sections.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-search.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-regions.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/hints.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-changes.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-history.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-stage.js')) ?>" defer></script>
