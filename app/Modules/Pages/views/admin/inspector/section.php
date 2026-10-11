<?php

use App\Modules\Design\SectionStyle;
use App\Modules\Pages\SectionLayout;
use App\Support\Controls;

/**
 * THE SECTION'S INSPECTOR (PLAN.md D-175, README 4.5): Columns, Background, Spacing & height,
 * Content, Advanced, and its actions — every control the admin's own (App\Support\Controls),
 * with the dot and ↺ against what the character composes for the blocks it holds.
 *
 * Each control is named by where its value lives in the document: `s.layout`, `s.stack`,
 * `s.style.surface`… builder-inspector.js writes the document from that name, and a ↺ is a
 * button whose value is the name to give back to the character.
 *
 * @var array{key: string, id: int|null, layout: string, stack: string, style: array<string, string|int|null>} $section
 * @var list<array<string, mixed>> $blocks the blocks it holds
 * @var string $character
 * @var array<string, array{0: string, 1: string}> $swatches surface => the two colours drawn for it
 * @var list<array{id: int, name: string, thumb: string|null}> $pictures
 * @var int $number its place on the page, from 1
 * @var int $gap the design's section gap in px, which a padding left to the character falls to
 * @var array<string, string> $design the design's resolved decisions: a section of columns
 *      composes wide where its columns need it (D-184)
 * @var \App\Core\Blocks $registry
 */
$style = $section['style'];
$composed = \App\Modules\Pages\SectionRender::composed($character, $blocks, \App\Modules\Pages\SectionLayout::normalize($section['layout']), $design);
$effective = SectionStyle::effective($style, $composed);
$name = (string) ($style[SectionStyle::NAME] ?? '');
$reset = static fn (string $path): array => ['form' => '', 'name' => 'reset', 'value' => $path, 'title' => t('controls.reset')];
$row = static function (string $label, string $control, string $path, bool $changed, string $readout = '', bool $following = false) use ($reset): string {
    return Controls::row($label, $control, ['key' => $path, 'labelId' => 'ins-' . str_replace('.', '-', $path) . '-label', 'changed' => $changed, 'readout' => $readout, 'following' => $following] + ($changed ? ['reset' => $reset($path)] : []));
};
$own = static fn (string $key): bool => (string) ($style[$key] ?? '') !== '';
$labelOf = static fn (string $key): string => 'ins-s-style-' . $key . '-label';

// ---- Columns ----------------------------------------------------------------------------------
$columnTiles = [];
foreach (SectionLayout::LAYOUTS as $layout => $shares) {
    $total = array_sum($shares);
    $x = 2;
    $parts = '';
    foreach ($shares as $share) {
        $width = (int) floor((44 - 2 * (count($shares) - 1)) * $share / $total);
        $parts .= '<rect class="pict-image" x="' . $x . '" y="5" width="' . $width . '" height="14" rx="1"/>';
        $x += $width + 2;
    }
    $columnTiles[$layout] = ['label' => t('style.layout.short.' . $layout), 'title' => t('style.layout.' . $layout), 'picture' => '<svg viewBox="0 0 48 24" focusable="false" aria-hidden="true"><rect class="pict-ground" width="48" height="24" rx="3"/>' . $parts . '</svg>'];
}
$stacks = [];
foreach (SectionLayout::STACKS as $stack) {
    $stacks[$stack] = t('style.stack.short.' . $stack);
}
$columns = $row(t('style.layout'), Controls::tiles('s.layout', $columnTiles, $section['layout'], 'ins-s-layout-label', 'ins-s-layout-'), 's.layout', false, t('style.layout.short.' . $section['layout']))
    . (SectionLayout::columns($section['layout']) > 1
        ? $row(t('style.stack'), segmented_group('s.stack', $stacks, $section['stack'], 'ins-s-stack-label', 'ins-s-stack-'), 's.stack', false)
        : '');

// ---- Background ---------------------------------------------------------------------------------
$surfaces = [];
foreach (SectionStyle::OPTIONS['surface'] as $surface) {
    [$from, $to] = $swatches[$surface];
    $fill = $from === $to ? 'fill="' . e($from) . '"' : 'fill="url(#ins-g-' . e($surface) . ')"';
    $gradient = $from === $to ? '' : '<defs><linearGradient id="ins-g-' . e($surface) . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . e($from) . '"/><stop offset="1" stop-color="' . e($to) . '"/></linearGradient></defs>';
    $surfaces[$surface] = ['label' => short_label('style.surface', $surface), 'picture' => '<svg viewBox="0 0 48 24" focusable="false" aria-hidden="true">' . $gradient . '<rect width="48" height="24" rx="3" ' . $fill . '/></svg>'];
}
$background = $row(t('style.surface'), Controls::tiles('s.style.surface', $surfaces, (string) $effective['surface'], $labelOf('surface'), 'ins-s-surface-'), 's.style.surface', $own('surface'), short_label('style.surface', (string) $effective['surface']));
if ($effective['surface'] === 'image') {
    $options = '<option value="">' . e(t('pages.field.media_none')) . '</option>';
    foreach ($pictures as $picture) {
        $options .= '<option value="' . e((string) $picture['id']) . '"' . ((string) ($style[SectionStyle::IMAGE] ?? '') === (string) $picture['id'] ? ' selected' : '') . '>' . e($picture['name']) . '</option>';
    }
    $background .= Controls::row(t('style.image'), '<select id="ins-s-image" name="s.style.image">' . $options . '</select>', ['key' => 's.style.image', 'for' => 'ins-s-image', 'hint' => '<p class="hint">' . e(t('style.image_hint')) . '</p>']);
}

// ---- Spacing & height ------------------------------------------------------------------------------
// THE SLIDER STANDS AT THE REAL VALUE (D-176). A padding left to the character is the
// character's number, or the design's section gap where the character leaves it too; the
// slider stands there, the readout says "80 px · character", and the dot comes only when the
// slider is moved. It stood at 0 before, which read as "no room at all".
$spacing = '';
foreach (SectionStyle::NUMBERS as $key => $range) {
    $value = (string) $effective[$key];
    if ($value === '' && str_starts_with($key, 'pad_')) {
        $value = (string) $gap;
    }
    $shown = $key === 'min_height'
        ? ($value === '0' || $value === '' ? t('style.min_height.auto') : $value . '%')
        : $value . ' px';
    $at = $value === '' ? (string) $range['min'] : $value;
    $spacing .= $row(t('style.' . $key), Controls::slider('s.style.' . $key, 'ins-s-' . $key, $at, $range['min'], $range['max'], $range['step']), 's.style.' . $key, $own($key), $shown, true);
}
if ((int) $effective['min_height'] > 0) {
    $spacing .= $row(t('style.v_align'), segmented_group('s.style.v_align', array_combine(SectionStyle::OPTIONS['v_align'], array_map(static fn (string $v): string => short_label('style.v_align', $v), SectionStyle::OPTIONS['v_align'])), (string) $effective['v_align'], $labelOf('v_align'), 'ins-s-v_align-'), 's.style.v_align', $own('v_align'));
}

// ---- Content ---------------------------------------------------------------------------------------
$content = '';
foreach (['width', 'align', 'divider'] as $key) {
    $labels = [];
    foreach (SectionStyle::OPTIONS[$key] as $value) {
        $labels[$value] = short_label('style.' . $key, $value);
    }
    $content .= $row(t('style.' . $key), segmented_group('s.style.' . $key, $labels, (string) $effective[$key], $labelOf($key), 'ins-s-' . $key . '-'), 's.style.' . $key, $own($key));
    // WIDTH THAT THE TEXT DOES NOT FOLLOW (D-187, D-188, the owner): a block held to a line
    // length — Text, Questions, a Quote — widens with the section only up to it, and Width
    // looked broken. Said here, with the way to the block's own option.
    if ($key === 'width') {
        foreach ($blocks as $held) {
            $type = (string) ($held['type'] ?? '');
            $specs = $registry->has($type) ? $registry->get($type)['options'] : [];
            // The layout it is drawn in: its own, or the character's where it follows (D-191).
            $heldLayout = (string) ($held['layout'] ?? '');
            $heldLayout = $heldLayout !== '' || !isset($specs['measure']) ? $heldLayout : \App\Modules\Design\Composition::layout($registry, $character, $type);
            if (!isset($specs['measure']) || !\App\Core\BlockOptions::applies($specs['measure'], $heldLayout)) {
                continue;
            }
            $measure = \App\Core\BlockOptions::effective($specs, \App\Core\BlockOptions::normalize($specs, $held['options'] ?? []), \App\Modules\Design\Composition::options($character, $type))['measure'];
            if ($measure !== 'full') {
                $content .= '<p class="hint-line" data-width-measure>' . e(t('builder.width_measure', ['measure' => t('block.' . $type . '.option.measure.' . $measure)])) . ' <button type="button" class="link-button" data-action="select-block" data-key="' . e((string) $held['key']) . '" data-focus="measure">' . e(t('builder.width_measure_link')) . '</button></p>';
                break;
            }
        }
    }
}

// ---- Advanced -----------------------------------------------------------------------------------
$anchor = (string) ($style[SectionStyle::ANCHOR] ?? '');
$hidden = '';
foreach (SectionStyle::HIDDEN as $key) {
    $hidden .= '<label class="toggle-chip"><input type="checkbox" name="s.style.' . e($key) . '" value="yes"' . (($style[$key] ?? '') === 'yes' ? ' checked' : '') . '><span>' . e(t('style.' . $key)) . '</span></label>';
}
$everywhere = count(array_filter(SectionStyle::HIDDEN, static fn (string $key): bool => ($style[$key] ?? '') === 'yes')) === count(SectionStyle::HIDDEN);
$animations = [];
foreach (SectionStyle::OPTIONS['animation'] as $value) {
    $animations[$value] = short_label('style.animation', $value);
}
$advanced = Controls::row(t('style.name'), '<input type="text" id="ins-s-name" name="s.style.name" maxlength="' . SectionStyle::NAME_LENGTH . '" value="' . e($name) . '" placeholder="' . e(t('builder.section_n', ['n' => $number])) . '">', ['key' => 's.style.name', 'for' => 'ins-s-name'])
    . Controls::row(t('style.anchor'), '<input type="text" id="ins-s-anchor" name="s.style.anchor" maxlength="' . SectionStyle::ANCHOR_LENGTH . '" value="' . e($anchor) . '" autocapitalize="off" spellcheck="false">', ['key' => 's.style.anchor', 'for' => 'ins-s-anchor', 'hint' => '<p class="hint-line" data-anchor-link>' . e($anchor === '' ? t('builder.anchor_hint') : t('builder.anchor_link', ['link' => '#' . $anchor])) . '</p>'])
    . Controls::row(t('style.hide'), '<div class="toggle-chips" role="group" aria-labelledby="ins-s-hide-label">' . $hidden . '</div>', ['labelId' => 'ins-s-hide-label', 'hint' => $everywhere ? '<p class="hint-warning">' . e(t('style.hidden_everywhere')) . '</p>' : ''])
    . $row(t('style.animation'), segmented_group('s.style.animation', $animations, (string) $effective['animation'], $labelOf('animation'), 'ins-s-animation-'), 's.style.animation', $own('animation'));

$count = static fn (array $keys): int => count(array_filter($keys, $own));
$changed = $count(SectionStyle::composed());
?>
<div class="inspector-head">
    <span class="inspector-icon" aria-hidden="true"><?= icon('rows-3') ?></span>
    <div class="inspector-titles">
        <h2 class="inspector-name"><?= e($name !== '' ? $name : t('builder.section_n', ['n' => $number])) ?></h2>
        <p class="inspector-context"><?= e(t(count($blocks) === 1 ? 'builder.section_context_one' : 'builder.section_context', ['count' => count($blocks)])) ?></p>
    </div>
<?php if ($changed > 0): ?>
    <button type="button" class="link-button inspector-reset" data-reset-all="section"><?= icon('history') ?> <?= e(t('builder.reset_to_character')) ?></button>
<?php endif; ?>
</div>
<?= Controls::group('ins-columns', t('builder.group.columns'), $columns) ?>
<?= Controls::group('ins-background', t('builder.group.background'), $background, ['changed' => $count(['surface'])]) ?>
<?= Controls::group('ins-spacing', t('builder.group.spacing'), $spacing, ['changed' => $count(['pad_top', 'pad_bottom', 'min_height', 'v_align'])]) ?>
<?= Controls::group('ins-content', t('builder.group.content'), $content, ['changed' => $count(['width', 'align', 'divider'])]) ?>
<?= Controls::group('ins-advanced', t('builder.group.advanced'), $advanced, ['changed' => $count(['animation']), 'open' => false]) ?>
<div class="inspector-actions">
    <button type="button" class="link-button" data-action="pattern"><?= icon('bookmark-plus') ?> <?= e(t('builder.save_pattern')) ?></button>
    <button type="button" class="link-button" data-action="duplicate"><?= icon('copy') ?> <?= e(t('builder.duplicate_section')) ?></button>
    <button type="button" class="link-button link-danger" data-action="delete"><?= icon('trash-2') ?> <?= e(t('builder.delete_section')) ?></button>
</div>
