<?php

// Admin labels derive from the type, through t(): block.picture, block.picture.<field>,
// block.picture.layout.<layout>.
//
// ONE PICTURE AND NOTHING ELSE (PLAN.md D-105). Until sections had columns there was no
// use for it: a picture alone in a full-width band is a poster, and `image_text` covered
// the case of a picture with words beside it. In a column it is the commonest thing there
// is — a portrait beside a paragraph, a photograph in the third column — and no block in
// the set could say it.
//
// The layout is how tall the picture is allowed to be, not how wide: the column decides
// the width, and a picture that chose its own would fight the arrangement around it.
return [
    'type' => 'picture',
    'icon' => 'frame',
    'group' => 'media',
    'version' => 1,
    'fields' => [
        'image' => ['type' => 'media'],
        'caption' => ['type' => 'text', 'translatable' => true, 'sample' => 'preview.picture.caption'],
        'shape' => ['type' => 'select', 'options' => ['natural', 'wide', 'square', 'round']],
    ],
    'layouts' => ['full', 'inset'],
    'defaults' => ['layout' => 'full'],
];
