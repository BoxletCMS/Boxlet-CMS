<?php

// Admin labels derive from the type, through t(): block.accordion, block.accordion.<field>,
// block.accordion.items.<itemfield>, block.accordion.layout.<layout>.
//
// QUESTIONS AND ANSWERS, FOLDED (PLAN.md D-105, review §2.3). Twenty answers written into one
// Text block is a page nobody reads; the same twenty behind their own questions is a page
// somebody scans. It is the commonest thing a small site has that Boxlet could not say.
//
// NO JAVASCRIPT. <details> and <summary> open and close by themselves, work before any script
// has loaded, print open, and are what a screen reader already knows. A folding panel built
// out of a div and a click handler is the version that breaks.
return [
    'type' => 'accordion',
    'icon' => 'circle-help',
    'group' => 'text',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.accordion.heading'],
        'items' => [
            'type' => 'repeater',
            'required' => true,
            'max' => 20,
            'fields' => [
                'question' => ['type' => 'text', 'required' => true, 'translatable' => true, 'sample' => 'preview.accordion.question'],
                'answer' => ['type' => 'richtext', 'translatable' => true, 'sample' => 'preview.accordion.answer'],
            ],
        ],
        // A list that opens closed says "there is more here"; one with the first answer
        // showing says "here is how this works". Both are right, on different pages.
        'start' => ['type' => 'select', 'options' => ['closed', 'first-open']],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'list' => ['line' => [[6, 4, 28, 2], [6, 10, 28, 2], [6, 16, 28, 2]], 'logo' => [[39, 4, 3, 2], [39, 10, 3, 2], [39, 16, 3, 2]]],
        'cards' => ['image' => [[4, 2, 40, 6], [4, 9, 40, 6], [4, 16, 40, 6]], 'line' => [[7, 4, 24, 2], [7, 11, 24, 2], [7, 18, 24, 2]]],
    ],
    'defaults' => ['layout' => 'list'],
    'options' => [
        // How wide its lines run (D-188, the owner, as the Text block's): its answers were held to
        // 38em whatever the section's Width. The heading keeps the same edge.
        'measure' => ['values' => ['comfortable', 'wide', 'full'], 'default' => 'comfortable'],
    ],
];
