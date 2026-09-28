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
        // The two below are for the arrangements with the picture BEHIND the words
        // (PLAN.md D-118), and closed sets like everything else a block offers: how tall
        // the block stands, and how much of the contrast colour is washed over the picture.
        // Every strength keeps the words at 4.5:1 over a picture of any brightness, under
        // every character (tests/hero_cover_test.php), so none of them is a way to make it
        // unreadable.
        'height' => ['type' => 'select', 'options' => ['content', 'tall', 'screen']],
        'veil' => ['type' => 'select', 'options' => ['light', 'medium', 'strong']],
    ],
    'layouts' => ['left', 'center', 'split', 'cover-center', 'cover-left', 'cover-right', 'cover-low'],
    'defaults' => ['layout' => 'center'],
];
