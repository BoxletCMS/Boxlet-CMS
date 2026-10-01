<?php

// An SVG logo, checked and cleaned on upload (PLAN.md D-142).
return [
    'svg.or' => 'Or an SVG',
    'svg.hint' => 'Sharp at any size. Export it with its text turned into outlines: shown as a picture, an SVG cannot use the site\'s fonts and draws text in another. It is cleaned on upload: anything that is not drawing, such as scripts or links to other sites, is taken out. An SVG here is used instead of the picture above.',
    'svg.upload' => 'Upload SVG',
    'svg.replace' => 'Replace SVG',
    'svg.send' => 'Upload',
    'svg.current' => 'This SVG is the logo now.',
    'svg.remove' => 'Remove SVG',
    'svg.stored' => 'The SVG logo is saved.',
    'svg.removed' => 'The SVG logo is removed. The picture chosen above, if any, is the logo again.',
    'svg.no_file' => 'Choose an SVG file first.',
    'svg.cannot_write' => 'The SVG could not be saved. Check that the web server may write to public/m.',
    'svg.too_large' => 'This SVG is larger than :size KB. A logo is usually a few kilobytes; export it without embedded pictures or fonts.',
    'svg.doctype' => 'This SVG declares a DOCTYPE, which a logo never needs and Boxlet does not accept. Export it again as plain SVG.',
    'svg.not_svg' => 'This file is not an SVG, or it is damaged.',
    'svg.no_size' => 'This SVG has no viewBox, so its shape is unknown. Export it with a viewBox (most design tools do by default).',
];
