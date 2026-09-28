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
            'per_layout' => ['two' => 2, 'three' => 3, 'four' => 4],
            'fields' => [
                'image' => ['type' => 'media'],
                'caption' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.gallery.caption'],
            ],
        ],
        // One shape for all of them: pictures taken on different days in different
        // proportions make a ragged grid, and cropping them to agree is the whole point.
        'shape' => ['type' => 'select', 'options' => ['square', 'wide', 'natural', 'round']],
    ],
    'layouts' => ['two', 'three', 'four'],
    'defaults' => ['layout' => 'three'],
];
