<?php

// The site's footer (PLAN.md D-028, D-030, D-113). One per site and one per page, discovered
// by the chrome registry over app/Chrome rather than app/Blocks.
//
// It owns the only <footer> on the page, which is why the language switcher moved into it
// rather than staying a second footer in the layout. The switcher is not a field: it is
// drawn from the enabled locales, and it draws nothing at all when there is only one.
//
// The menu arrives in $resolved, like the header's — see app/Chrome/header/block.php. Since
// D-113 it may be a menu of the footer's own, or none; the renderer decides, the template
// draws what it is handed.
//
// Each column's words are RICH TEXT since D-113, with the footer's short whitelist
// (RichText::INLINE): a line or two with a link in it.
//
// The layouts are the footer's arrangements (D-113).

return [
    'type' => 'footer',
    'icon' => 'footer',
    'version' => 1,
    'fields' => [
        // Up to three columns of content (D-115): a title and words each. A column's menu
        // is not a field — menus are resolved by the layout and arrive in $resolved.
        'columns' => [
            'type' => 'repeater',
            'max' => 3,
            'fields' => [
                'title' => ['type' => 'text', 'translatable' => true],
                'text' => ['type' => 'richtext', 'translatable' => true],
            ],
        ],
        'small_print' => ['type' => 'text', 'translatable' => true],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'simple' => ['line' => [[4, 5, 20, 2], [4, 10, 14, 2], [4, 18, 10, 2]]],
        'centred' => ['line' => [[14, 5, 20, 2], [17, 10, 14, 2], [19, 18, 10, 2]]],
        'columns' => ['line' => [[4, 5, 16, 2], [4, 9, 12, 2], [28, 5, 10, 2], [28, 9, 8, 2], [28, 13, 9, 2], [4, 19, 40, 1]]],
        'menu_first' => ['line' => [[4, 5, 7, 2], [13, 5, 7, 2], [22, 5, 7, 2], [4, 11, 22, 2], [4, 18, 10, 2]]],
        'three' => ['line' => [[4, 5, 11, 2], [4, 9, 8, 2], [19, 5, 10, 2], [19, 9, 8, 2], [33, 5, 11, 2], [33, 9, 7, 2]]],
    ],
    'defaults' => ['layout' => 'simple'],
];
