<?php

/**
 * The Appearance screen's inspector (PLAN.md D-157, D-158, D-160): its home, its sections and
 * groups, the owner's changes over the character, and the contrast check in a line. The
 * decisions keep their words in design.php and the header and footer in chrome.php.
 */

return [
    'inspector.home' => 'All settings',
    'inspector.quick' => 'Quick start',
    'inspector.character' => 'Character',
    'inspector.detailed' => 'Detailed',
    'inspector.back' => 'All settings',
    'inspector.export' => 'Export',
    'inspector.delete' => 'Delete',
    'inspector.use' => 'Use',
    'inspector.tile_menu' => 'More for “:name”',

    'inspector.section.colours' => 'Colours',
    'inspector.section.typography' => 'Typography',
    'inspector.section.space' => 'Space & shape',
    'inspector.section.layout' => 'Layout & widths',
    'inspector.section.header' => 'Header',
    'inspector.section.footer' => 'Footer',

    'inspector.group.colours.basics' => 'Basics',
    'inspector.group.colours.palette' => 'Palette',
    'inspector.group.colours.contrast' => 'Contrast',
    'inspector.group.typography.typeface' => 'Typeface',
    'inspector.group.typography.sizes' => 'Sizes',
    'inspector.group.typography.headings' => 'Headings',
    'inspector.group.typography.fine' => 'Fine-tuning',
    'inspector.group.space.spacing' => 'Spacing',
    'inspector.group.space.shape' => 'Shape',
    'inspector.group.space.shadow' => 'Shadow',
    'inspector.group.layout.diagram' => 'Overview',
    'inspector.group.layout.content' => 'Content',
    'inspector.group.layout.page' => 'Page',
    'inspector.group.layout.chrome' => 'Header & footer',
    'inspector.group.header.arrangement' => 'Arrangement',
    'inspector.group.header.behaviour' => 'Behaviour',
    'inspector.group.header.background' => 'Background',
    'inspector.group.header.menu' => 'Menu & button',
    'inspector.group.footer.arrangement' => 'Arrangement',
    'inspector.group.footer.background' => 'Background',

    // The line under each section's name, built from the values on the screen.
    'inspector.summary.colours' => ':seed · :passing/:total readable',
    'inspector.summary.typography' => ':fonts · :size px · scale :scale',
    'inspector.summary.space' => 'spacing :space px · corners :radius px · shadow :shadow',
    'inspector.summary.layout_boxed' => 'sheet :sheet px · content :content px',
    'inspector.summary.layout_full' => 'full width · content :content px',

    'inspector.layout.sheet' => 'Sheet :sheet px',
    'inspector.layout.full_width' => 'Full width',
    'inspector.layout.content' => 'Content :content px',
    'inspector.layout.chrome' => 'Header & footer',

    // How much is the owner's own, over the character the screen shows (D-158).
    'inspector.overrides_one' => ':count own change over :character',
    'inspector.overrides_many' => ':count own changes over :character',
    'inspector.reset.one' => 'Put “:name” back as the character has it',
    'inspector.reset.section' => 'Reset section to character',
    'inspector.reset.all' => 'Reset all',
    'inspector.reset.done' => 'Put back as the character has it. The site has not changed — press Publish for that.',
    'inspector.reset.all_done' => 'Everything is back as the character has it. The site has not changed — press Publish for that.',

    // Publish's question after a character is loaded (D-068), with a way out of it (D-161).
    'inspector.apply.restyles_one' => 'One section you styled by hand goes back to the character.',
    'inspector.apply.restyles_many' => ':count sections you styled by hand go back to the character.',
    'inspector.apply.own_layout_one' => 'One block with a layout of your own keeps it.',
    'inspector.apply.own_layouts_many' => ':count blocks with a layout of their own keep it.',
    'inspector.apply.cancel' => 'Cancel',
    'inspector.apply.cancel_hint' => 'Back to the design as it is published. Nothing changes on the site.',

    'inspector.palette_note' => 'Worked out from the main colour. Pick a swatch to set your own — the dot shows it is yours.',
    'inspector.palette_all' => 'Show the other :count roles',

    // The contrast check in one line (D-160).
    'inspector.contrast.ok' => 'All pairs are readable',
    'inspector.contrast.fail_one' => ':count pair is too low — Publish is blocked',
    'inspector.contrast.fail_many' => ':count pairs are too low — Publish is blocked',
    'inspector.contrast.fix' => 'Fix automatically',
    'inspector.contrast.change_seed' => 'Some pairs fail because of the main or second colour itself. Change that colour to fix them.',
    'inspector.contrast.every' => 'Every pair (:count)',

    // Global decisions v2 (D-164).
    'inspector.section.buttons' => 'Buttons',
    'inspector.group.buttons.style' => 'Style',
    'inspector.summary.buttons' => ':style · :corners',
];
