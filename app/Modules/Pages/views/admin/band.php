<?php

/**
 * One band, drawn for the canvas, and its own fields (PLAN.md D-099, D-101).
 *
 * Two templates, because they go to two places: the drawing replaces the band in the canvas
 * and the fields join [data-section-groups], where a band's fields live once however many
 * blocks stand in it. A redraw uses only the first; adding a band uses both.
 *
 * Template elements are inert: the browser parses them without running scripts, loading
 * images or applying styles until the editor moves them where they belong.
 *
 * @var string $bandHtml
 * @var array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null} $sectionOf
 * @var array<string, string|int|null> $composed
 * @var list<array{id: int, name: string, thumb: string|null}> $pictures
 * @var \App\Core\Blocks $registry
 */
?>
<template data-band-canvas><?= $bandHtml ?></template>
<template data-section-fields><div class="panel-section" data-section-group="<?= e($sectionOf['key']) ?>" hidden>
<?php require __DIR__ . '/section.php'; ?>
</div></template>
