<?php

// Admin labels derive from the type, through t(): block.quote, block.quote.<field>,
// block.quote.layout.<layout>.
//
// WHAT PEOPLE TYPE INTO A TEXT BLOCK AS ITALICS TODAY (review §2.3). A testimonial has a
// shape — the words, who said them, and often their face — and a Text block can hold all
// three only as prose that every character then styles as prose.
//
// The words are a textarea and not rich text on purpose: a quotation is a quotation. Bold
// and links inside one are almost always somebody working around its not being a Text
// block, and the design layer cannot promise anything about what they look like at pull-
// quote size.
return [
    'type' => 'quote',
    'icon' => 'quote',
    'group' => 'marketing',
    'version' => 1,
    'fields' => [
        'quote' => ['type' => 'textarea', 'required' => true, 'translatable' => true, 'sample' => 'preview.quote.quote'],
        'attribution' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.quote.attribution'],
        'role' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.quote.role'],
        'portrait' => ['type' => 'media'],
    ],
    'layouts' => ['plain', 'card'],
    'defaults' => ['layout' => 'plain'],
];
