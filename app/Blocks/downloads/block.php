<?php

// Admin labels derive from the type, through t(): block.downloads, block.downloads.<field>,
// block.downloads.items.<itemfield>, block.downloads.layout.<layout>.
//
// FILES FOR VISITORS TO TAKE AWAY (PLAN.md D-127, O-17): a price list, a form to fill in,
// a brochure. Each item is a file from the library with a title and a line about it; the
// block draws the file's type and size beside it, from the file itself, so they are never
// typed and never wrong. An item whose file has not been chosen is not drawn on the page —
// a download that downloads nothing is a broken promise — and shows in the editor as a
// place to fill.
return [
    'type' => 'downloads',
    'icon' => 'file-text',
    'group' => 'media',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.downloads.heading'],
        'items' => [
            'type' => 'repeater',
            'required' => true,
            'max' => 20,
            'fields' => [
                'file' => ['type' => 'file'],
                // What the visitor reads. Empty, the file's own name stands in, so a file can
                // be offered without anything typed at all.
                'title' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.downloads.title'],
                'description' => ['type' => 'textarea', 'translatable' => true, 'sample' => 'preview.downloads.description'],
            ],
        ],
    ],
    // A list reads down, one file under another; cards set them side by side, for a few
    // files of equal weight.
    'layouts' => ['list', 'cards'],
    'defaults' => ['layout' => 'list'],
];
