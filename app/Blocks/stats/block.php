<?php

// Admin labels derive from the type, through t(): block.stats, block.stats.<field>,
// block.stats.items.<itemfield>, block.stats.layout.<layout>.
//
// THE NUMBERS A SITE LEADS WITH (PLAN.md D-105, review §2.3). "12 years", "300 clients",
// "48 hours" — written into a Columns block today, where the number is a heading and takes
// heading size, which is not the size a number wants. Here the number is the content and the
// character sizes it as one.
//
// The number is TEXT, not a number field. "300+", "~48h" and "1 in 3" are all things people
// legitimately write, and a numeric field would refuse every one of them to buy a validation
// nobody needed.
return [
    'type' => 'stats',
    'icon' => 'chart-column',
    'group' => 'marketing',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.stats.heading'],
        'items' => [
            'type' => 'repeater',
            'required' => true,
            'max' => 8,
            'fields' => [
                'value' => ['type' => 'text', 'required' => true, 'translatable' => true, 'sample' => 'preview.stats.value'],
                'label' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.stats.label'],
            ],
        ],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'three' => ['logo' => [[6, 7, 8, 4], [20, 7, 8, 4], [34, 7, 8, 4]], 'line' => [[6, 13, 10, 1], [20, 13, 10, 1], [34, 13, 10, 1]]],
        'four' => ['logo' => [[4, 7, 7, 4], [15, 7, 7, 4], [26, 7, 7, 4], [37, 7, 7, 4]], 'line' => [[4, 13, 8, 1], [15, 13, 8, 1], [26, 13, 8, 1], [37, 13, 8, 1]]],
    ],
    'defaults' => ['layout' => 'three'],
    // How many stand side by side in each layout (D-189): the inspector says when the items
    // leave one alone in the last row.
    'across' => ['three' => 3, 'four' => 4],
];
