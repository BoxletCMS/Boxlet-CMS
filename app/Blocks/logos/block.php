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
    'layouts' => ['row', 'grid'],
    'defaults' => ['layout' => 'row'],
];
