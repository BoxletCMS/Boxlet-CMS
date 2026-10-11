<?php

use App\Modules\Design\Characters;
use App\Modules\Design\Presets;
use App\Support\Url;

/**
 * WHAT THE SCREEN HAS TO SAY OR ASK, at the top of the inspector (PLAN.md D-157): what the
 * last press did, a character the site was composed with that is gone (D-156), a design file
 * refused, and the two questions — how to publish a character, what to do with an imported
 * file. (Loading a character no longer asks: it keeps the owner's changes, D-164.)
 *
 * NOT A BAND ACROSS THE SCREEN ANY MORE. The questions stood under the bar at the full width
 * of the window and pushed the picture down, which is the one thing a question about the
 * design should not do. They sit where the answer is given, above the controls, and each
 * still says what every answer does beside it. Without a script they are part of the page
 * like everything else.
 *
 * @var string|null $notice
 * @var array<string, string> $errors
 * @var string|null $missingCharacter
 * @var list<string> $importErrors
 * @var array{set: array<string, mixed>, warnings: list<string>}|null $import
 * @var bool $confirm
 * @var int $restyled how many sections hold something set by hand, which Apply hands back (D-165)
 * @var int $ownLayouts how many blocks have a layout of the owner's own, which Apply keeps (D-191)
 * @var string $character
 * @var Closure(array<string, string>, string, string, string): string $card
 */
?>
                <div class="inspector-panels">
<?php if ($notice !== null): ?>
                    <p class="notice appearance-notice<?= $errors !== [] ? ' notice-error' : '' ?>" role="<?= $errors !== [] ? 'alert' : 'status' ?>"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($missingCharacter !== null): ?>
                    <p class="notice appearance-notice" role="status"><?= e(t('appearance.character_missing', ['name' => $missingCharacter, 'default' => Characters::label(Presets::DEFAULT)])) ?></p>
<?php endif; ?>
<?php if ($importErrors !== []): ?>
                    <div class="notice appearance-notice notice-error" role="alert">
                        <p><?= e(t('appearance.import_refused', ['reason' => ''])) ?></p>
                        <ul class="import-list">
<?php foreach ($importErrors as $reason): ?>
                            <li><?= e($reason) ?></li>
<?php endforeach; ?>
                        </ul>
                    </div>
<?php endif; ?>
<?php if ($confirm): ?>
                    <?php /* The one destructive choice in the design layer (D-068), asked once,
                             with what each answer does written beside it. */ ?>
                    <div class="appearance-confirm publish-confirm" role="alert">
                        <p class="confirm-question"><?= e(t('design.apply.title', ['character' => Characters::label($character)])) ?></p>
                        <div class="confirm-options">
                            <span>
                                <button type="submit" form="design-form" name="action" value="save_design" class="button button-secondary"><?= e(t('design.apply.design_only')) ?></button>
                                <span class="hint hint-always"><?= e(t('design.apply.design_only_hint')) ?></span>
                            </span>
                            <span>
                                <button type="submit" form="design-form" name="action" value="save_composition" class="button"><?= e(t('design.apply.with_composition')) ?></button>
                                <span class="hint hint-always"><?= e(t('design.apply.with_composition_hint')) ?></span>
<?php if ($restyled > 0): ?>
                                <span class="hint hint-always" data-restyled><?= e(t($restyled === 1 ? 'inspector.apply.restyles_one' : 'inspector.apply.restyles_many', ['count' => (string) $restyled])) ?></span>
<?php endif; ?>
<?php if ($ownLayouts > 0): ?>
                                <span class="hint hint-always" data-own-layouts><?= e(t($ownLayouts === 1 ? 'inspector.apply.own_layout_one' : 'inspector.apply.own_layouts_many', ['count' => (string) $ownLayouts])) ?></span>
<?php endif; ?>
                            </span>
                            <?php /* A WAY OUT (D-161): the screen as the site is published, which is
                                     the state before the character was loaded. A link, so it
                                     posts nothing. */ ?>
                            <span>
                                <a class="button button-quiet" href="<?= e(Url::admin('appearance')) ?>" data-apply-cancel><?= e(t('inspector.apply.cancel')) ?></a>
                                <span class="hint hint-always"><?= e(t('inspector.apply.cancel_hint')) ?></span>
                            </span>
                        </div>
                    </div>
<?php endif; ?>
<?php if ($import !== null):
    $set = $import['set'];
    $name = (string) ($set['name']['en'] ?? reset($set['name']));
    ?>
                    <?php /* A design file brought in (D-152): what it is, what was left out, and
                             each answer with what it does. */ ?>
                    <div class="appearance-confirm import-confirm" role="alert">
                        <p class="confirm-question"><?= e(t('appearance.import_question', ['name' => $name])) ?></p>
                        <?= $card($set['decisions'], (string) ($set['look']['header_arrangement'] ?? ''), $name, 'import') ?>
<?php if ($import['warnings'] !== []): ?>
                        <p class="hint hint-always"><?= e(t('appearance.import_warnings')) ?></p>
                        <ul class="import-list hint hint-always">
<?php foreach ($import['warnings'] as $warning): ?>
                            <li><?= e($warning) ?></li>
<?php endforeach; ?>
                        </ul>
<?php endif; ?>
<?php if ($set['composition'] === null): ?>
                        <p class="hint hint-always"><?= e(t('appearance.import_design_only')) ?></p>
<?php endif; ?>
                        <div class="confirm-options">
<?php if ($set['composition'] !== null): ?>
                            <span>
                                <button type="submit" form="design-import-add" class="button"><?= e(t('appearance.import_add')) ?></button>
                                <span class="hint hint-always"><?= e(t('appearance.import_add_hint')) ?></span>
                            </span>
<?php endif; ?>
                            <span>
                                <button type="submit" form="design-form" name="action" value="import:load" class="button button-secondary"><?= e(t('appearance.import_load')) ?></button>
                                <span class="hint hint-always"><?= e(t('appearance.import_load_hint')) ?></span>
                            </span>
                            <span>
                                <a class="button button-quiet" href="<?= e(Url::admin('appearance')) ?>"><?= e(t('appearance.import_cancel')) ?></a>
                            </span>
                        </div>
                    </div>
<?php endif; ?>
                </div>
