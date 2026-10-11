<?php

use App\Modules\Appearance\Overrides;
use App\Modules\Design\Characters;
use App\Modules\Design\Presets;
use App\Modules\Design\Vocabulary\Decisions;
use App\Support\Controls;
use App\Support\Url;

/**
 * THE INSPECTOR'S HOME (PLAN.md D-157): where to start from, how much is the owner's own,
 * and the six sections, each with a line saying what is in it now.
 *
 * WITHOUT A SCRIPT this is the top of one long column, the sections stand under it, and each
 * row of the list is a link down to its section. With one (appearance-sections.js) it is a
 * view of its own and a row opens its section in its place.
 *
 * Included by appearance.php inside the inspector, with the closures of parts/controls.php
 * and cards.php in scope.
 *
 * @var string $basis the character the screen measures against
 * @var string $activeCharacter
 * @var list<string> $changed
 * @var array<string, string> $readouts
 * @var array<string, string> $decisions
 * @var Closure(array<string, string>, string): string $chips
 * @var Closure(string, string, array<string, mixed>=): array<string, mixed> $rowOptions
 * @var Closure(): string $pairingTiles
 * @var Closure(string, list<string>): array<string, string> $labels
 * @var array<string, string> $icons each section's icon
 */
/**
 * A question Quick start repeats from its section, for a script to mirror both ways: the
 * real field is the one in the section, and this one belongs to the empty form
 * #appearance-quick, so the two are not one radio group (D-157). Never shown without a script.
 */
$mirror = static function (string $key) use ($decisions, $rowOptions, $readouts): string {
    $definition = Decisions::ALL[$key];
    $id = 'quick-' . $key;
    if ($definition['type'] === 'number') {
        $marks = [];
        foreach ($definition['marks'] ?? [] as $mark => $value) {
            $marks[(string) $value] = t('design.' . $key . '.' . $mark);
        }

        return Controls::row(t('design.' . $key), Controls::slider($key, $id, $decisions[$key] ?? '', (float) $definition['min'], (float) $definition['max'], (float) $definition['step'], $marks, ['form' => 'appearance-quick']),
            ['for' => $id, 'readout' => $readouts[$key] ?? '', 'readoutKey' => $key, 'hint' => '', 'error' => ''] + $rowOptions($key, t('design.' . $key)));
    }
    $labels = [];
    foreach ($definition['values'] ?? [] as $value) {
        $labels[$value] = t('design.' . $key . '.' . $value);
    }

    return Controls::row(t('design.' . $key), segmented_group($key, $labels, $decisions[$key] ?? '', $id . '-label', $id . '-', 'appearance-quick'),
        ['labelId' => $id . '-label', 'hint' => '', 'error' => ''] + $rowOptions($key, t('design.' . $key)));
};

ob_start();
?>
                        <div class="control-row character-row">
                            <div class="control-head"><span class="control-label" id="quick-character-label"><?= e(t('inspector.character')) ?></span></div>
                            <?php /* THE CHARACTERS AS TILES (D-157): the column they had is the room the
                                     preview needed. Every one the registry holds — Boxlet's, a file
                                     the owner put in designs/custom, one imported — three to a row,
                                     the name cut with … where it is long and whole in its title. */ ?>
                            <ul class="character-tiles" role="list" aria-labelledby="quick-character-label">
<?php foreach (Presets::names() as $preset): ?>
<?php $name = Characters::label($preset); ?>
                                <li class="character-tile<?= $preset === $basis ? ' is-current' : '' ?>">
                                    <?= $chips(Presets::get($preset), 'preset-' . $preset) ?>
                                    <span class="tile-name" title="<?= e($name) ?>"><?= e($name) ?></span>
                                    <span class="tile-tags"><?php if ($preset === $activeCharacter): ?><span class="tile-live"><?= e(t('design.preset.current')) ?></span><?php endif; ?><?php if (Characters::source($preset) !== 'core'): ?><span class="tile-custom"><?= e(t('appearance.custom')) ?></span><?php endif; ?></span>
                                    <?php /* The whole tile loads it, under the menu rather than over it. */ ?>
                                    <button type="submit" form="design-form" name="action" value="preset:<?= e($preset) ?>" class="tile-use"<?= $preset === $basis ? ' aria-current="true"' : '' ?>>
                                        <span class="visually-hidden"><?= e(t('design.load_preset')) ?>: <?= e($name) ?></span></button>
                                    <?php /* ⋯ is a <details>, so it opens with no script: Export for every
                                             character, Delete for one that was imported (D-152). */ ?>
                                    <details class="tile-menu">
                                        <summary class="icon-button" title="<?= e(t('inspector.tile_menu', ['name' => $name])) ?>"><?= icon('ellipsis-vertical') ?><span class="visually-hidden"><?= e(t('inspector.tile_menu', ['name' => $name])) ?></span></summary>
                                        <div class="tile-menu-items">
                                            <a href="<?= e(Url::admin('appearance', 'export', 'character', $preset)) ?>" title="<?= e(t('appearance.export_one', ['name' => $name])) ?>"><?= icon('download') ?> <?= e(t('inspector.export')) ?></a>
<?php if (Characters::source($preset) === 'imported'): ?>
                                            <button type="submit" form="design-form" name="action" value="character:delete:<?= e($preset) ?>"
                                                    data-confirm="<?= e(t('appearance.character_delete_confirm', ['name' => $name])) ?>" title="<?= e(t('appearance.character_delete', ['name' => $name])) ?>"><?= icon('trash-2') ?> <?= e(t('inspector.delete')) ?></button>
<?php endif; ?>
                                        </div>
                                    </details>
                                </li>
<?php endforeach; ?>
                            </ul>
                            <?php /* MORE OF THEM (D-195): the sets Boxlet ships beside these, added one by one. */ ?>
                            <a class="button button-secondary library-browse" href="<?= e(Url::admin('appearance', 'browse')) ?>"><?= icon('palette') ?> <?= e(t('browse.button')) ?></a>
                        </div>
                        <div class="quick-mirrors" data-quick hidden>
                            <?= Controls::row(t('design.seed'), '<div class="colour-field"><input type="color" class="colour-input" id="quick-seed" name="seed" form="appearance-quick" value="' . e($decisions['seed']) . '">'
                                . '<output class="colour-value" for="quick-seed" data-colour-for="quick-seed">' . e($decisions['seed']) . '</output></div>',
                                ['for' => 'quick-seed', 'hint' => '', 'error' => ''] + $rowOptions('seed', t('design.seed'))) ?>
                            <?= $mirror('mode') ?>
                            <?php /* The pairings, which set the two families in Typography (D-185). */ ?>
                            <?= Controls::row(t('design.pairings'), $pairingTiles(), ['hint' => '', 'error' => '']) ?>
                            <?= $mirror('text_size') ?>
                            <?= $mirror('radius') ?>
                            <?= $mirror('spacing') ?>
                        </div>
<?php $quick = (string) ob_get_clean(); ?>
                <section class="inspector-view inspector-home" id="appearance-home" data-view="home" aria-labelledby="appearance-home-title">
                    <h2 class="visually-hidden" id="appearance-home-title"><?= e(t('inspector.home')) ?></h2>
                    <?= Controls::group('group-quick', t('inspector.quick'), $quick, ['changed' => count(array_intersect($changed, ['seed', 'mode', 'heading_font', 'body_font', 'text_size', 'radius', 'spacing']))]) ?>

                    <?php /* HOW MUCH IS THE OWNER'S OWN (D-158): shown only when something is, and
                             with the one press that gives all of it back. The count and the words
                             are kept live by appearance-overrides.js from the two templates. */ ?>
                    <div class="overrides" data-overrides<?= $changed === [] ? ' hidden' : '' ?>>
                        <span class="control-changed" aria-hidden="true"></span>
                        <span data-overrides-text data-one="<?= e(t('inspector.overrides_one', ['character' => Characters::label($basis)])) ?>" data-many="<?= e(t('inspector.overrides_many', ['character' => Characters::label($basis)])) ?>"><?= e(t(count($changed) === 1 ? 'inspector.overrides_one' : 'inspector.overrides_many', ['count' => count($changed), 'character' => Characters::label($basis)])) ?></span>
                        <button type="submit" form="design-form" name="action" value="reset:all" class="button button-quiet"><?= e(t('inspector.reset.all')) ?></button>
                    </div>

                    <h2 class="inspector-heading"><?= e(t('inspector.detailed')) ?></h2>
                    <?php /* LINKS, so every section is reachable with no script: down the page
                             without one, into the section's own view with one. */ ?>
                    <ul class="section-list" role="list">
<?php foreach (array_keys(Overrides::SECTIONS) as $section): ?>
<?php $count = Overrides::count($changed, $section); ?>
                        <li><a class="section-link<?= $count > 0 ? ' has-changes' : '' ?>" href="#section-<?= e($section) ?>" data-open-section="<?= e($section) ?>">
                            <?= icon($icons[$section]) ?>
                            <span class="section-link-text"><span class="section-link-name"><?= e(t('inspector.section.' . $section)) ?></span>
                                <span class="section-link-summary" data-readout="summary.<?= e($section) ?>"><?= e($readouts['summary.' . $section] ?? '') ?></span></span>
                            <span class="section-count" data-section-count="<?= e($section) ?>" title="<?= e(t('controls.changed_count')) ?>"><?= $count ?></span>
                            <span class="section-link-go"><?= icon('chevron-left') ?></span>
                        </a></li>
<?php endforeach; ?>
                    </ul>

<?php require __DIR__ . '/designs.php'; ?>
                </section>
