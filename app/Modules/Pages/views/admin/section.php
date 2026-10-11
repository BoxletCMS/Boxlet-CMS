<?php
/**
 * ONE SECTION'S OWN FIELDS: the columns it holds, what they do on a phone, and the five
 * style keys plus the background picture (PLAN.md D-095, D-097, D-099, D-107).
 *
 * ITS OWN PARTIAL, AND THAT IS THE POINT. These fields used to be rendered inside each
 * block's group, which was exact while a section held exactly one block and duplicated
 * every control the moment a second joined — two `<select name="sections[s7][style][surface]">`
 * on one form, editing one not moving the other, and the last in the document deciding what
 * is saved. Nobody would have seen it until they set a surface on the wrong half of a band.
 *
 * TWO PLACEMENTS, ONE DEFINITION. The visual editor renders these into a group of their own
 * and its Section tab (D-086) shows the one belonging to the selected block's section. The
 * plain editor renders them where they have always been, folded at the foot of the first
 * block of the band.
 *
 * EVERY CLOSED SET IS A ROW OF BUTTONS (D-107), the control the Appearance screen has had
 * since D-065 — because this is the same question asked about a band. A <select> hides its
 * options until pressed, so the one thing an owner wants to know while looking at a page —
 * what else this could be — took a click per field. Radios, so the form still submits
 * without a script, the names and values posted are unchanged, and a screen reader is told
 * it is a radio group rather than a listbox.
 *
 * @var array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null} $sectionOf
 * @var array<string, string> $composed what the character composes for this section: what
 *      each "Auto" stands for; the band is open when the owner has set anything over it
 * @var list<array{id: int, name: string, thumb: string|null}> $pictures
 */
$sectionPrefix = 'sections[' . $sectionOf['key'] . ']';
$sectionIdPrefix = 'section-' . $sectionOf['key'] . '-';
$style = \App\Modules\Design\SectionStyle::normalize($sectionOf['style']);
// What media-picker.js needs, on the field itself: the admin's CSP allows no inline script
// and these survive being cloned out of a <template>. Defined here rather than inherited
// from block.php, because the visual editor requires this partial on its own (D-099).
$pickerAttributes = static fn (): string => \App\Modules\Media\MediaReference::pickerAttributes();
$layout = $sectionOf['layout'] ?? \App\Modules\Pages\SectionLayout::ONE;
?>
                <details class="block-style" data-panel-part="section"<?= \App\Modules\Design\SectionStyle::overridden($style) ? ' open' : '' ?>>
                    <summary><?= e(t('style.title')) ?></summary>
<?php if (($sectionOf['id'] ?? null) !== null): ?>
                    <input type="hidden" name="<?= e($sectionPrefix) ?>[id]" value="<?= e((string) $sectionOf['id']) ?>">
<?php endif; ?>
                    <div class="block-style-grid">
                        <?php /* THE ARRANGEMENT FIRST (D-097, D-099), because it is the one
                                 choice here that changes the SHAPE of the band rather than
                                 its colouring, and because it is what the empty column that
                                 asks to be filled comes from. A closed set, never a
                                 percentage — the argument SectionStyle makes about colour. */ ?>
                        <div class="field">
                            <div class="choice-head">
                                <span class="choice-name" id="<?= e($sectionIdPrefix) ?>layout-label"><?= e(t('style.layout')) ?></span>
                                <?php /* THE ONLY READOUT HERE, and it earns its place: these
                                         buttons are a diagram and a piece of notation, so the
                                         words have nowhere else to go. It is drawn by the
                                         server and the server redraws this whole partial
                                         whenever the layout changes (redrawBand), which is
                                         the only thing that can change it — so it is right
                                         without a script keeping it so. The other groups
                                         spell their value on the button that is pressed. */ ?>
                                <span class="choice-value" data-readout="layout"><?= e(t('style.layout.' . $layout)) ?></span>
                            </div>
                            <?php /* A SHAPE, NOT A WORD, which is what SectionLayout::LAYOUTS
                                     has said its weights were for since it was written: each
                                     button draws the columns in proportion, with the notation
                                     the page outline already uses under it. Drawn from the
                                     weights rather than from seven hand-written rules, so a
                                     layout added to that list arrives here already drawn. */ ?>
                            <div class="segmented-choice cols-grid" role="radiogroup" aria-labelledby="<?= e($sectionIdPrefix) ?>layout-label">
<?php foreach (\App\Modules\Pages\SectionLayout::LAYOUTS as $layoutName => $weights): ?>
                                <label class="segment cols-option">
                                    <input type="radio" id="<?= e($sectionIdPrefix . 'layout-' . $layoutName) ?>" name="<?= e($sectionPrefix) ?>[layout]" value="<?= e($layoutName) ?>"<?= $layout === $layoutName ? ' checked' : '' ?> aria-label="<?= e(t('style.layout.' . $layoutName)) ?>">
                                    <span class="cols-figure" aria-hidden="true">
<?php foreach ($weights as $weight): ?><i class="w<?= e((string) $weight) ?>"></i><?php endforeach; ?>
                                    </span>
                                    <span aria-hidden="true"><?= e(t('style.layout.short.' . $layoutName)) ?></span>
                                </label>
<?php endforeach; ?>
                            </div>
                            <?= field_hint('hint.style.layout') ?>
                        </div>
                        <?php
                        /* AND THE REST, EACH A ROW OF BUTTONS. `stack` is an arrangement and
                           the style keys are a colouring, but to the person choosing they are
                           one kind of question, so they wear one kind of control.

                           AUTO FIRST, AND IT SAYS WHAT IT IS (D-165). Every style key is ''
                           until the owner sets it, and '' is the character's answer — so the
                           first button is that answer by name, and pressing it hands the key
                           back. `stack` has no Auto: it is the owner's arrangement, which a
                           character has no opinion on. */
                        $groups = ['stack' => \App\Modules\Pages\SectionLayout::STACKS];
                        foreach (\App\Modules\Design\SectionStyle::OPTIONS as $styleKey => $styleValues) {
                            $groups[$styleKey] = $styleValues;
                        }
                        foreach ($groups as $groupKey => $groupValues):
                            $isStyle = $groupKey !== 'stack';
                            $name = $sectionPrefix . ($isStyle ? '[style][' . $groupKey . ']' : '[' . $groupKey . ']');
                            $current = (string) ($isStyle ? ($style[$groupKey] ?? '') : ($sectionOf[$groupKey] ?? ''));
                            $labels = [];
                            if ($isStyle) {
                                $labels[''] = t('style.auto', ['value' => short_label('style.' . $groupKey, (string) ($composed[$groupKey] ?? \App\Modules\Design\SectionStyle::DEFAULTS[$groupKey]))]);
                            }
                            foreach ($groupValues as $groupValue) {
                                $labels[$groupValue] = short_label('style.' . $groupKey, $groupValue);
                            }
                            $labelId = $sectionIdPrefix . $groupKey . '-label';
                        ?>
                        <div class="field">
                            <div class="choice-head">
                                <span class="choice-name" id="<?= e($labelId) ?>"><?= e(t('style.' . $groupKey)) ?></span>
                            </div>
                            <?= segmented_group($name, $labels, $current, $labelId, $sectionIdPrefix . $groupKey . '-', '', 'auto') ?>
                            <?= field_hint('hint.style.' . $groupKey) ?>
                        </div>
<?php endforeach; ?>
                        <?php /* THE SPACING AND THE HEIGHT, as numbers on a step (D-165). Empty is
                                 Auto, and the placeholder says what Auto comes to. Not sliders
                                 yet: a slider cannot be empty, and the builder's own controls
                                 arrive with its rebuild. */ ?>
<?php foreach (\App\Modules\Design\SectionStyle::NUMBERS as $numberKey => $range):
    $composedNumber = (string) ($composed[$numberKey] ?? '');
    $autoWords = $composedNumber === '' ? t('style.pad.gap') : ($numberKey === 'min_height' && $composedNumber === '0' ? t('style.min_height.auto') : $composedNumber);
?>
                        <div class="field">
                            <label for="<?= e($sectionIdPrefix . 'style-' . $numberKey) ?>"><?= e(t('style.' . $numberKey)) ?></label>
                            <input type="number" id="<?= e($sectionIdPrefix . 'style-' . $numberKey) ?>" name="<?= e($sectionPrefix) ?>[style][<?= e($numberKey) ?>]" value="<?= e((string) ($style[$numberKey] ?? '')) ?>" min="<?= e((string) $range['min']) ?>" max="<?= e((string) $range['max']) ?>" step="<?= e((string) $range['step']) ?>" placeholder="<?= e(t('style.auto', ['value' => $autoWords])) ?>" inputmode="numeric">
                            <?= field_hint('hint.style.' . $numberKey) ?>
                        </div>
<?php endforeach; ?>
                        <fieldset class="field">
                            <legend><?= e(t('style.hide')) ?></legend>
<?php foreach (\App\Modules\Design\SectionStyle::HIDDEN as $hiddenKey): ?>
                            <label class="checkbox"><input type="checkbox" name="<?= e($sectionPrefix) ?>[style][<?= e($hiddenKey) ?>]" value="yes"<?= ($style[$hiddenKey] ?? '') === 'yes' ? ' checked' : '' ?>> <?= e(t('style.' . $hiddenKey)) ?></label>
<?php endforeach; ?>
<?php if (($style['hide_desktop'] ?? '') === 'yes' && ($style['hide_tablet'] ?? '') === 'yes' && ($style['hide_mobile'] ?? '') === 'yes'): ?>
                            <p class="hint hint-warning"><?= e(t('style.hidden_everywhere')) ?></p>
<?php endif; ?>
                            <?= field_hint('hint.style.hide') ?>
                        </fieldset>
                        <div class="field">
                            <label for="<?= e($sectionIdPrefix . 'style-anchor') ?>"><?= e(t('style.anchor')) ?></label>
                            <input type="text" id="<?= e($sectionIdPrefix . 'style-anchor') ?>" name="<?= e($sectionPrefix) ?>[style][anchor]" value="<?= e((string) ($style['anchor'] ?? '')) ?>" maxlength="<?= e((string) \App\Modules\Design\SectionStyle::ANCHOR_LENGTH) ?>" spellcheck="false" autocapitalize="off">
                            <?= field_hint('hint.style.anchor') ?>
                        </div>
                        <div class="field">
                            <label for="<?= e($sectionIdPrefix . 'style-name') ?>"><?= e(t('style.name')) ?></label>
                            <input type="text" id="<?= e($sectionIdPrefix . 'style-name') ?>" name="<?= e($sectionPrefix) ?>[style][name]" value="<?= e((string) ($style['name'] ?? '')) ?>" maxlength="<?= e((string) \App\Modules\Design\SectionStyle::NAME_LENGTH) ?>">
                            <?= field_hint('hint.style.name') ?>
                        </div>
                        <?php /* D-024's sixth key. Not part of OPTIONS, because OPTIONS is
                                 what becomes class names on the wrapper and a picture is
                                 rendered, not painted. A <select> and not a row of buttons:
                                 the set is not closed — it is every picture in the library —
                                 which is the whole distinction the control is drawing. */ ?>
                        <div class="field block-style-picture">
                            <label for="<?= e($sectionIdPrefix . 'style-image') ?>"><?= e(t('style.image')) ?></label>
                            <select id="<?= e($sectionIdPrefix . 'style-image') ?>" name="<?= e($sectionPrefix) ?>[style][<?= e(\App\Modules\Design\SectionStyle::IMAGE) ?>]" data-media-field<?= $pickerAttributes() ?>>
                                <option value=""><?= e(t('pages.field.media_none')) ?></option>
<?php $surfaceImage = (int) ($style[\App\Modules\Design\SectionStyle::IMAGE] ?? 0); ?>
<?php foreach ($pictures as $picture): ?>
                                <option value="<?= e($picture['id']) ?>"<?= $picture['thumb'] === null ? '' : ' data-thumb="' . e($picture['thumb']) . '"' ?><?= $surfaceImage === $picture['id'] ? ' selected' : '' ?>><?= e($picture['name']) ?></option>
<?php endforeach; ?>
                            </select>
                            <span class="hint"><?= e(t('style.image_hint')) ?></span>
                        </div>
                    </div>
                </details>
