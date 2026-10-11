<?php

// Admin labels derive from the type, through t(): block.gallery, block.gallery.<field>,
// block.gallery.items.<itemfield>, block.gallery.layout.<layout>.
//
// SEVERAL PICTURES IN A ROW (PLAN.md D-105, review §2.3). A Columns block can hold pictures,
// but each column is a picture WITH words under it, and every one of them carries the weight
// of a heading and a paragraph. A gallery is the other thing: the pictures are the content.
//
// The layout is how many share a row, the same vocabulary a Columns block uses, because it is
// the same question and an owner should not have to learn it twice. More than fit in a row
// wrap into the next one.
return [
    'type' => 'gallery',
    'icon' => 'images',
    'group' => 'media',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.gallery.heading'],
        'items' => [
            'type' => 'repeater',
            'required' => true,
            // Six rows of four. Past that it is an album, which wants paging and a lightbox
            // and is a module rather than a block.
            'max' => 24,
            'fields' => [
                'image' => ['type' => 'media'],
                'caption' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.gallery.caption'],
            ],
        ],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'two' => ['image' => [[4, 4, 19, 16], [25, 4, 19, 16]]],
        'three' => ['image' => [[4, 6, 12, 12], [18, 6, 12, 12], [32, 6, 12, 12]]],
        'four' => ['image' => [[4, 7, 9, 10], [14, 7, 9, 10], [25, 7, 9, 10], [35, 7, 9, 10]]],
    ],
    'defaults' => ['layout' => 'three'],
    // How many stand side by side in each layout (D-189): the inspector says when the items
    // leave one alone in the last row.
    'across' => ['two' => 2, 'three' => 3, 'four' => 4],
    'options' => [
        // One shape for all of them: pictures taken on different days in different
        // proportions make a ragged grid, and cropping them to agree is the whole point. An
        // option since D-166, so a character may have its own.
        'shape' => ['values' => ['square', 'wide', 'natural', 'round'], 'default' => 'square'],
    ],
];
