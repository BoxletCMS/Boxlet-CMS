<?php

// Admin labels derive from the type, through t(): block.logos, block.logos.<field>,
// block.logos.items.<itemfield>, block.logos.layout.<layout>.
//
// WHO ELSE TRUSTS THEM (PLAN.md D-105, review §2.3). A row of client marks is a Gallery
// today, and it comes out wrong every time: a gallery crops to a shape so the grid lines up,
// and cropping a logo is the one thing nobody is allowed to do to one.
//
// So the pictures are NEVER cropped and never cover their box. They are set to one height
// and left at their own width, which is how a row of marks is laid out by anybody who has
// laid one out. That is the whole reason this is a block and not a Gallery with a switch.
return [
    'type' => 'logos',
    'icon' => 'building-2',
    'group' => 'marketing',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.logos.heading'],
        'items' => [
            'type' => 'repeater',
            'required' => true,
            'max' => 18,
            'fields' => [
                'image' => ['type' => 'media'],
                // The name is what a picture that has not arrived shows, and what a screen
                // reader reads: a wall of marks with nothing to read is a wall of nothing.
                'name' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.logos.name'],
                'link' => ['type' => 'link', 'translatable' => true, 'sample' => 'preview.logos.link'],
            ],
        ],
    ],
    // A row runs across and wraps; a grid gives every mark the same cell, which suits marks
    // of very different widths.
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'row' => ['image' => [[6, 10, 7, 4], [16, 10, 7, 4], [26, 10, 7, 4], [36, 10, 7, 4]]],
        'grid' => ['image' => [[6, 6, 7, 4], [16, 6, 7, 4], [26, 6, 7, 4], [36, 6, 7, 4], [6, 14, 7, 4], [16, 14, 7, 4], [26, 14, 7, 4], [36, 14, 7, 4]]],
    ],
    'defaults' => ['layout' => 'row'],
];
