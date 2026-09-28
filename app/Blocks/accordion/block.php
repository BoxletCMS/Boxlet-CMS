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
    'layouts' => ['list', 'cards'],
    'defaults' => ['layout' => 'list'],
];
