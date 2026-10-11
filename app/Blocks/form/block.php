<?php

// Admin labels derive from the type, through t(): block.form, block.form.<field>,
// block.form.layout.<layout>.
//
// A form on a page (PLAN.md D-046). The form itself — its fields, its button, what it does
// when sent — is made on the Forms screen; this block chooses one and gives it a heading
// and a sentence, and the section styles every block has.
return [
    'type' => 'form',
    'icon' => 'clipboard-list',
    'group' => 'marketing',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.form.heading'],
        'intro' => ['type' => 'textarea', 'translatable' => true, 'sample' => 'preview.form.intro'],
        'form' => ['type' => 'form'],
    ],
    // Stacked: the heading above the form. Beside: the heading and sentence in one column
    // and the form in the other, which is how a contact section is usually set.
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'stacked' => ['line' => [[12, 2, 20, 2]], 'image' => [[12, 6, 24, 3], [12, 11, 24, 3]], 'button' => [[12, 16, 10, 4]]],
        'beside' => ['line' => [[4, 5, 16, 3], [4, 10, 12, 1]], 'image' => [[24, 4, 20, 3], [24, 9, 20, 3]], 'button' => [[24, 15, 10, 4]]],
    ],
    'defaults' => ['layout' => 'stacked'],
];
