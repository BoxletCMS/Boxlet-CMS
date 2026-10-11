<?php

use App\Modules\Design\Characters;
use App\Support\Controls;
use App\Support\Url;

/**
 * YOUR DESIGNS (PLAN.md D-061, D-152, D-157): the designs the owner keeps, keeping the one on
 * the screen, and a design brought in as a file. At the foot of the home, where the rail's
 * second half used to be a column of its own. Required by inspector/home.php, in its scope.
 *
 * @var list<array{id: int, name: string, character: string, decisions: array<string, string>, look: array<string, string>}> $library
 * @var list<array{file: string, reason: string}> $skipped the custom design files left out
 * @var Closure(string): string $error
 * @var Closure(array<string, string>, string): string $chips
 */
ob_start();
?>
                        <ul class="design-rows" role="list">
<?php foreach ($library as $saved): ?>
                            <li class="design-row">
                                <?= $chips(App\Modules\Design\Tokens::resolve($saved['decisions'] + $saved['look'], $saved['character']), 'saved-' . $saved['id']) ?>
                                <span class="design-row-text">
                                    <span class="design-row-name" title="<?= e($saved['name']) ?>"><?= e($saved['name']) ?></span>
                                    <span class="design-row-from"><?= e(Characters::exists($saved['character']) ? t('appearance.library.from', ['character' => Characters::label($saved['character'])]) : t('appearance.library.by_hand')) ?></span>
                                </span>
                                <button type="submit" form="design-form" name="action" value="library:use:<?= e((string) $saved['id']) ?>" class="button button-quiet"><?= e(t('inspector.use')) ?><span class="visually-hidden">: <?= e($saved['name']) ?></span></button>
                                <?php /* Overwrite: the name comes from the design itself, so "save what
                                         is on screen into this one" cannot be mistyped. */ ?>
                                <button type="submit" form="design-form" name="action" value="library:save:<?= e((string) $saved['id']) ?>" class="icon-button" title="<?= e(t('appearance.library.overwrite', ['name' => $saved['name']])) ?>"><?= icon('replace') ?><span class="visually-hidden"><?= e(t('appearance.library.overwrite', ['name' => $saved['name']])) ?></span></button>
                                <a class="icon-button" href="<?= e(Url::admin('appearance', 'export', 'library', $saved['id'])) ?>" title="<?= e(t('appearance.export_one', ['name' => $saved['name']])) ?>"><?= icon('download') ?><span class="visually-hidden"><?= e(t('appearance.export_one', ['name' => $saved['name']])) ?></span></a>
                                <button type="submit" form="design-form" name="action" value="library:delete:<?= e((string) $saved['id']) ?>" class="icon-button" title="<?= e(t('appearance.library.delete_one', ['name' => $saved['name']])) ?>"><?= icon('trash-2') ?><span class="visually-hidden"><?= e(t('appearance.library.delete_one', ['name' => $saved['name']])) ?></span></button>
                            </li>
<?php endforeach; ?>
                        </ul>
<?php if ($library === []): ?>
                        <p class="hint hint-always"><?= e(t('appearance.library.empty')) ?></p>
<?php endif; ?>
                        <div class="design-keep">
                            <div class="field">
                                <label class="visually-hidden" for="library_name"><?= e(t('appearance.library.name')) ?></label>
                                <input type="text" id="library_name" name="library_name" maxlength="80" value="" autocomplete="off"
                                       placeholder="<?= e(t('appearance.library.name')) ?>">
                                <?= $error('library_name') ?>
                            </div>
                            <button type="submit" form="design-form" name="action" value="library:save" class="button button-secondary"><?= e(t('appearance.library.save')) ?></button>
                        </div>

                        <?php /* A DESIGN AS A FILE, IN (D-152). The file input and its button stand
                                 here but post the import form outside this one, by their form
                                 attribute: a form inside a form is not a form. The browser's own
                                 file control is hidden and a label in the admin's words opens it
                                 (D-142); choosing a file sends it (file-sends.js), and without a
                                 script the button beside it does. */ ?>
                        <div class="design-import">
                            <input type="file" id="design-file" name="design" accept="application/json,.json" form="design-import" class="visually-hidden" data-file-sends>
                            <label for="design-file" class="button button-secondary"><?= icon('cloud-upload') ?> <?= e(t('appearance.import')) ?></label>
                            <button type="submit" form="design-import" class="button no-js-only"><?= e(t('appearance.import_send')) ?></button>
                            <span class="hint"><?= e(t('appearance.import_label')) ?></span>
                        </div>
<?php if ($skipped !== []): ?>
                        <?php /* Custom files the registry left out (D-155): a notice, not an error —
                                 the site runs without them. */ ?>
                        <div class="notice design-skipped" role="status">
                            <p><?= e(t('appearance.skipped')) ?></p>
                            <ul class="import-list">
<?php foreach ($skipped as $entry): ?>
                                <li><strong><?= e($entry['file']) ?></strong> — <?= e($entry['reason']) ?></li>
<?php endforeach; ?>
                            </ul>
                        </div>
<?php endif; ?>
<?php $designs = (string) ob_get_clean(); ?>
                    <?= Controls::group('group-designs', t('appearance.library'), $designs) ?>
