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
    'layouts' => ['image-left', 'image-right'],
    'defaults' => ['layout' => 'image-left'],
];
