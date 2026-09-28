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
    'layouts' => ['banner', 'beside'],
    'defaults' => ['layout' => 'banner'],
];
