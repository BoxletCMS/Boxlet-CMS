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
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'single' => ['line' => [[10, 5, 28, 2], [10, 9, 28, 1], [10, 12, 26, 1], [10, 15, 28, 1], [10, 18, 20, 1]]],
        'columns' => ['line' => [[4, 6, 18, 1], [4, 9, 17, 1], [4, 12, 18, 1], [4, 15, 14, 1], [26, 6, 18, 1], [26, 9, 17, 1], [26, 12, 18, 1], [26, 15, 12, 1]]],
    ],
    'defaults' => ['layout' => 'single'],
    'options' => [
        // How wide a line of the text runs (D-187, the owner): about 65 characters, 85, or the
        // section's whole width. The heading keeps the same edge. In two columns it is each
        // column's (D-191, the owner).
        'measure' => ['values' => ['comfortable', 'wide', 'full'], 'default' => 'comfortable'],
    ],
];
