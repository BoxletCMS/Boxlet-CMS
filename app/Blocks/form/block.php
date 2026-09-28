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
    'layouts' => ['stacked', 'beside'],
    'defaults' => ['layout' => 'stacked'],
];
