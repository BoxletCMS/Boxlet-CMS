<?php

// Admin labels derive from the type, through t(): block.hero, block.hero.<field>,
// block.hero.layout.<layout>.
return [
    'type' => 'hero',
    'icon' => 'panel-top',
    'group' => 'marketing',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'required' => true, 'translatable' => true, 'sample' => 'preview.hero.heading'],
        'subheading' => ['type' => 'textarea', 'translatable' => true, 'sample' => 'preview.hero.subheading'],
        'image' => ['type' => 'media'],
        'cta' => ['type' => 'link', 'translatable' => true, 'sample' => 'preview.hero.cta'],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'left' => ['line' => [[4, 6, 22, 3], [4, 11, 18, 1]], 'button' => [[4, 15, 9, 4]]],
        'center' => ['line' => [[13, 6, 22, 3], [16, 11, 16, 1]], 'button' => [[19, 15, 10, 4]]],
        'split' => ['line' => [[4, 6, 18, 3], [4, 11, 16, 1]], 'button' => [[4, 15, 9, 4]], 'image' => [[26, 3, 18, 18]]],
        'cover-center' => ['image' => [[2, 2, 44, 20]], 'line' => [[13, 8, 22, 3], [16, 13, 16, 1]]],
        'cover-left' => ['image' => [[2, 2, 44, 20]], 'line' => [[6, 8, 20, 3], [6, 13, 14, 1]]],
        'cover-right' => ['image' => [[2, 2, 44, 20]], 'line' => [[22, 8, 20, 3], [28, 13, 14, 1]]],
        'cover-low' => ['image' => [[2, 2, 44, 20]], 'line' => [[6, 14, 22, 3], [6, 19, 14, 1]]],
    ],
    'defaults' => ['layout' => 'center'],
    // How it is presented, '' following the character (D-166). How tall the block stands —
    // as tall as its words, tall, or the screen — and, for the arrangements with the picture
    // BEHIND the words (D-118), how much of the contrast colour is washed over it. Every
    // strength keeps the words at 4.5:1 over a picture of any brightness, under every
    // character (tests/hero_cover_test.php), so none of them is a way to make it unreadable.
    'options' => [
        'height' => ['values' => ['auto', 'tall', 'screen'], 'default' => 'auto'],
        // Over a cover's picture; no other layout lays one (D-187).
        'veil' => ['values' => ['light', 'medium', 'strong'], 'default' => 'light', 'layouts' => ['cover-center', 'cover-left', 'cover-right', 'cover-low']],
    ],
];
