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
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'plain' => ['logo' => [[8, 5, 1, 12]], 'line' => [[11, 5, 26, 2], [11, 9, 22, 2], [11, 15, 10, 1]]],
        'big' => ['line' => [[6, 4, 36, 3], [6, 9, 30, 3], [6, 16, 10, 1]]],
        'card' => ['image' => [[6, 3, 36, 18]], 'line' => [[10, 7, 26, 2], [10, 11, 22, 2], [10, 16, 10, 1]]],
    ],
    'defaults' => ['layout' => 'plain'],
    'options' => [
        // How wide its lines run (D-188, the owner): wide by default, a quotation's words being
        // larger than a paragraph's. It followed the section's Width without limit, lines of
        // 1136px at Full.
        'measure' => ['values' => ['comfortable', 'wide', 'full'], 'default' => 'wide'],
    ],
];
