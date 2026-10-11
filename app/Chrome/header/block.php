<?php

// The site's header (PLAN.md D-028, D-030). Discovered by a SECOND registry over
// app/Chrome, not app/Blocks: it is drawn by the same machinery so it inherits the design
// tokens and the section style layers, but it must never appear in the page editor's block
// library — a header dropped into the middle of an article is not a thing.
//
// THERE IS NO MENU FIELD. The chrome screen chooses which menu by name, the renderer
// resolves it once per request, and the template receives it in $resolved — the same way
// pictures arrive already resolved, so a template asks the database nothing. No field type
// points at a menu and none was added: FIELD_TYPES is frozen (SPEC §5.3).
//
// The layouts are the header's ARRANGEMENTS (D-112): where the name, the menu and the
// button stand. What the bar does as the page scrolls — sticky, over the first section — is
// a second choice, carried in the look, because the two were one list once and a centred
// header could never be sticky.
//
// Two logos (D-112): the site's, and one for dark surfaces, used when the ink on the header
// is light. The renderer decides which; the template only draws.

return [
    'type' => 'header',
    'icon' => 'header',
    'version' => 1,
    'fields' => [
        'logo' => ['type' => 'media'],
        'logo_dark' => ['type' => 'media'],
        'button' => ['type' => 'link', 'translatable' => true],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'left' => ['logo' => [[4, 9, 8, 6]], 'line' => [[24, 11, 14, 2], [40, 10, 5, 4]]],
        'inline' => ['logo' => [[4, 9, 8, 6]], 'line' => [[15, 11, 14, 2], [39, 10, 5, 4]]],
        'centred' => ['logo' => [[19, 4, 10, 6]], 'line' => [[13, 15, 22, 2]]],
        'split' => ['logo' => [[20, 9, 8, 6]], 'line' => [[5, 11, 12, 2], [31, 11, 12, 2]]],
        'masthead' => ['logo' => [[4, 4, 12, 7]], 'line' => [[4, 15, 24, 2]]],
    ],
    'defaults' => ['layout' => 'left'],
];
