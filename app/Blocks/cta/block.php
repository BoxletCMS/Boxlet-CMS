<?php

// Admin labels derive from the type, through t(): block.cta, block.cta.<field>,
// block.cta.layout.<layout>.
//
// ASK FOR THE THING (PLAN.md D-105, review §2.3). Today this is a Hero block used a second
// time at the foot of a page, which is why every demo page ends with one: a Hero opens a
// page, and a block that opens a page is the wrong weight for one that closes it.
//
// TWO LINKS, BECAUSE A CHOICE IS THE POINT. "Book a call" is a big ask; "See the prices"
// beside it is what somebody not ready yet presses instead. The second is drawn quieter than
// the first without being asked, which is what the design layer is for.
return [
    'type' => 'cta',
    'icon' => 'megaphone',
    'group' => 'marketing',
    'version' => 1,
    'fields' => [
        'heading' => ['type' => 'text', 'required' => true, 'translatable' => true, 'sample' => 'preview.cta.heading'],
        'body' => ['type' => 'textarea', 'translatable' => true, 'sample' => 'preview.cta.body'],
        'action' => ['type' => 'link', 'translatable' => true, 'sample' => 'preview.cta.action'],
        'second' => ['type' => 'link', 'translatable' => true, 'sample' => 'preview.cta.second'],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'banner' => ['line' => [[12, 5, 24, 3], [16, 10, 16, 1]], 'button' => [[19, 14, 10, 4]]],
        'beside' => ['line' => [[4, 8, 22, 3], [4, 13, 18, 1]], 'button' => [[33, 9, 11, 5]]],
    ],
    'defaults' => ['layout' => 'banner'],
];
