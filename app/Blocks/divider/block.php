<?php

// Admin labels derive from the type, through t(): block.divider, block.divider.<field>,
// block.divider.layout.<layout>.
//
// RHYTHM CONTROL BETWEEN TWO BLOCKS THAT MUST NOT MERGE (review §2.3). A section's rhythm
// says how much air a whole band has; this says that these two things in one column are
// separate thoughts. It is the one block with nothing to write in it, which is why its
// only field is how much room it takes.
//
// A closed set of heights rather than a number, for the reason SectionStyle refuses a free
// colour: "24px" is a guess against a type scale the character owns, and it is wrong the
// moment the character changes.
return [
    'type' => 'divider',
    'icon' => 'separator-horizontal',
    'group' => 'layout',
    'version' => 1,
    'fields' => [
        // The first option is what a new one gets: a divider nobody has thought about
        // should be the quiet one.
        'height' => ['type' => 'select', 'options' => ['small', 'medium', 'large']],
    ],
    // Each layout with its drawing (D-166): part => [x, y, width, height] on 48 × 24.
    'layouts' => [
        'space' => ['line' => [[4, 3, 40, 2], [4, 19, 40, 2]]],
        'line' => ['line' => [[4, 3, 40, 2], [4, 19, 40, 2]], 'logo' => [[8, 11, 32, 1]]],
    ],
    'defaults' => ['layout' => 'space'],
];
