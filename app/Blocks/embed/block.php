<?php

// Admin labels derive from the type, through t(): block.embed, block.embed.<field>,
// block.embed.layout.<layout>.
//
// A VIDEO OR A MAP, FROM A LIST OF FOUR PLACES (PLAN.md D-105, review §2.3). Every small CMS
// eventually grows a "paste the embed code" box, and every one of them ships an XSS with it.
// Boxlet stores an ADDRESS and builds the iframe itself: App\Support\Embed parses the paste
// into a provider and an id, and the id goes into a fixed address. Nothing else can ever be
// framed, whatever is stored.
//
// So `url` is an ordinary text field. It is not validated on save — the closed field-type set
// (SPEC §5.3) has no type that could, and a block-by-block validation hook with one caller is
// the abstraction this project refuses. The editor says so instead: an address nothing
// recognises draws a note in the canvas, where the owner is looking when they paste it.
return [
    'type' => 'embed',
    'icon' => 'square-play',
    'group' => 'embed',
    'version' => 1,
    'fields' => [
        'url' => ['type' => 'text', 'required' => true, 'sample' => 'preview.embed.url'],
        'caption' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.embed.caption'],
        // The frame's proportions, because the provider cannot say: a video is wide, a map
        // is usually squarer, and neither knows what it is being put next to.
        'ratio' => ['type' => 'select', 'options' => ['wide', 'square', 'tall']],
    ],
    'layouts' => ['full', 'inset'],
    'defaults' => ['layout' => 'full'],
];
