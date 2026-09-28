<?php

// Admin labels derive from the type, through t(): block.text, block.text.<field>,
// block.text.layout.<layout>.
return [
    'type' => 'text',
    'icon' => 'type',
    'group' => 'text',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.text.heading'],
        'body' => ['type' => 'richtext', 'required' => true, 'translatable' => true, 'sample' => 'preview.text.body'],
    ],
    'layouts' => ['single', 'columns'],
    'defaults' => ['layout' => 'single'],
];
