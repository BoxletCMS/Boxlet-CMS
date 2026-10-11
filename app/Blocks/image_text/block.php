<?php

// Admin labels derive from the type, through t(): block.image_text, block.image_text.<field>,
// block.image_text.layout.<layout>.
// image stores a media id; until the Media module (Slice 5) it renders a placeholder.
return [
    'type' => 'image_text',
    'icon' => 'image',
    'group' => 'media',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.image_text.heading'],
        'body' => ['type' => 'richtext', 'required' => true, 'translatable' => true, 'sample' => 'preview.image_text.body'],
        'image' => ['type' => 'media'],
        'image_fit' => ['type' => 'select', 'options' => ['cover', 'contain']],
        'link' => ['type' => 'link', 'translatable' => true, 'sample' => 'preview.image_text.link'],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'image-left' => ['image' => [[4, 4, 18, 16]], 'line' => [[26, 7, 16, 2], [26, 11, 18, 1], [26, 14, 14, 1]]],
        'image-right' => ['image' => [[26, 4, 18, 16]], 'line' => [[4, 7, 16, 2], [4, 11, 18, 1], [4, 14, 14, 1]]],
    ],
    'defaults' => ['layout' => 'image-left'],
];
