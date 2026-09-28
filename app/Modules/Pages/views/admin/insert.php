<?php

/**
 * One new block, as the two pieces the editor needs: the section the canvas shows and
 * the field group the form submits. Both are rendered by the server from the block
 * definition, so the browser never needs to know what fields a block has.
 *
 * Template elements are inert: the browser parses them without running scripts, loading
 * images or applying styles until the editor moves them where they belong.
 *
 * @var int $index
 * @var array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string} $block
 * @var array<string, string> $errors
 * @var string $character
 * @var \App\Core\Blocks $registry
 * @var list<array{id: int, name: string, thumb: string|null}> $pictures
 * @var string $canvasHtml
 */
$showSection = false;
$sectionOf = [
    // A band of its own, named for this render only; the browser mints the real key when it
    // places the group, the same way it mints the block's (D-098).
    'key' => \App\Modules\Pages\SectionForm::key(null, 0),
    'id' => null,
    'layout' => \App\Modules\Pages\SectionLayout::ONE,
    'stack' => \App\Modules\Pages\SectionLayout::DEFAULT_STACK,
    'style' => $block['style'],
];
// What the character would compose for a band holding this one block (D-096), so a new
// band arrives with its style folded shut rather than announcing itself as hand-tuned.
$composed = \App\Modules\Design\Composition::section($character, [$block['type']]);
?>
<template data-block-canvas><?= $canvasHtml ?></template>
<template data-block-fields><div class="panel-block" data-block-group="<?= e($index) ?>" hidden>
<?php require __DIR__ . '/block.php'; ?>
</div></template>
<?php /* AND THE BAND, when the block is arriving as one of its own (D-099). Its own
         template because the two go to different places: the block's fields join
         [data-block-groups] and this joins [data-section-groups], where a band's fields
         live once however many blocks stand in it. A block going INTO an existing column
         has a band already, and the editor throws this away. */ ?>
<template data-section-fields><div class="panel-section" data-section-group="<?= e($sectionOf['key']) ?>" hidden>
<?php require __DIR__ . '/section.php'; ?>
</div></template>
