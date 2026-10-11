<?php

// Admin labels derive from the type, through t(): block.cards, block.cards.<field>,
// block.cards.items.<itemfield>, block.cards.layout.<layout>, block.cards.option.<option>.
//
// Cards (README 1.4, D-166; the Columns block of D-008, renamed): one block, a row of cards,
// each holding the same bounded content. No other block goes inside a card, and a page stays
// a flat list of blocks. How many stand in a row is an option, not how many there are: six
// cards in a row of three is two rows, which is how a team or a list of services is set.
return [
    'type' => 'cards',
    'icon' => 'columns-3',
    'group' => 'layout',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.cards.heading'],
        'intro' => ['type' => 'textarea', 'translatable' => true, 'sample' => 'preview.cards.intro'],
        'items' => [
            'type' => 'repeater',
            'required' => true,
            // Four rows of three: a team page or a list of services, and still an editor a
            // person can scroll through.
            'max' => 12,
            'fields' => [
                'image' => ['type' => 'media'],
                'heading' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.cards.item_heading'],
                'body' => ['type' => 'richtext', 'translatable' => true, 'sample' => 'preview.cards.item_body'],
                'link' => ['type' => 'link', 'translatable' => true, 'sample' => 'preview.cards.item_link'],
            ],
        ],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'grid' => ['image' => [[4, 4, 12, 7], [18, 4, 12, 7], [32, 4, 12, 7]], 'line' => [[4, 13, 10, 2], [18, 13, 10, 2], [32, 13, 10, 2], [4, 17, 12, 1], [18, 17, 12, 1], [32, 17, 12, 1]]],
        'list' => ['image' => [[4, 3, 8, 7], [4, 14, 8, 7]], 'line' => [[14, 4, 20, 2], [14, 8, 26, 1], [14, 15, 20, 2], [14, 19, 26, 1]]],
    ],
    'defaults' => ['layout' => 'grid'],
    // How it is presented, '' following the character (D-166).
    'options' => [
        // How many stand in a row: a list has one to a row (D-187).
        'per_row' => ['min' => 2, 'max' => 4, 'step' => 1, 'default' => 3, 'layouts' => ['grid']],
        // The picture area is part of a card (README 1.6): drawn as a placeholder until a
        // picture is chosen, unless the shape is none — a card of words.
        'image_shape' => ['values' => ['wide', 'square', 'round', 'none'], 'default' => 'wide'],
    ],
];
