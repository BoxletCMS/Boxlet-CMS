<?php

/**
 * The Appearance screen (PLAN.md D-059): what the merged screen itself says.
 *
 * The decisions keep their own words in design.php and the header and footer keep theirs in
 * chrome.php — the screens merged, the vocabulary did not have to. Only what is new here is
 * new: the five tabs, the publishing, and the one line about which language the preview
 * draws.
 */

return [
    'appearance.title' => 'Appearance',
    'appearance.subhead' => 'How the whole site looks, header and footer included',
    'appearance.library.overwrite' => 'Save what is on screen into “:name”',
    'appearance.back' => 'Leave Appearance',
    'appearance.stage.viewport' => ':width px',
    // Said rather than silently acted on: the screen used to change the width by itself when
    // the column ran out of room, and a picture that changes under your hand reads as a
    // design that changed.
    'appearance.stage.tight' => 'Too little room — choose a narrower width',
    // The last word of a card's summary: whether the page is a sheet or runs to the edges.
    'appearance.boxed' => 'boxed',
    'appearance.full_bleed' => 'full bleed',
    // SHORT, because six of them share 312px and a strip that wraps to two rows reads as
    // two strips. Each panel says the longer thing inside itself.
    // The strip over the picture: which page it is of (D-111).
    'appearance.page_to_preview' => 'Page to preview',
    'appearance.publish' => 'Publish',
    'appearance.published' => 'Published. The site looks like this from now on.',

    // The toolbar over the picture.
    'appearance.width' => 'Width',
    'appearance.width.desktop' => 'Desktop',
    'appearance.width.tablet' => 'Tablet',
    'appearance.width.phone' => 'Phone',
    'appearance.zoom' => 'Zoom',
    'appearance.zoom.fit' => 'Fit',
    'appearance.compare' => 'Compare',
    'appearance.compare_hint' => 'Hold to see the published site.',
    'appearance.state.published' => 'Published',
    'appearance.state.unpublished' => 'Not published yet',
    'appearance.search' => 'Search the settings',
    'appearance.search_placeholder' => 'Search settings — logo, sticky, corners…',
    'appearance.search_none' => 'No setting matches the search.',
    // What each control is found by besides its own words (D-181), by key.
    'appearance.keywords.seed' => 'accent brand primary main colour color',
    'appearance.keywords.mode' => 'dark light night theme',
    'appearance.keywords.secondary' => 'second colour accent two',
    'appearance.keywords.surface_contrast' => 'bands tint background contrast',
    'appearance.keywords.color_background' => 'page background',
    'appearance.keywords.color_card' => 'card box panel',
    'appearance.keywords.color_text' => 'ink body text font colour',
    'appearance.keywords.color_link' => 'links underline',
    'appearance.keywords.heading_font' => 'font typeface fonts serif sans display mono heading title',
    'appearance.keywords.body_font' => 'font typeface fonts serif sans mono text body paragraph',
    'appearance.keywords.text_size' => 'font size body bigger smaller',
    'appearance.keywords.scale' => 'headings size ratio',
    'appearance.keywords.line_height' => 'leading line spacing',
    'appearance.keywords.heading_weight' => 'bold heavy titles',
    'appearance.keywords.tracking' => 'letter spacing',
    'appearance.keywords.caps' => 'uppercase capitals',
    'appearance.keywords.spacing' => 'padding gaps whitespace room',
    'appearance.keywords.section_gap' => 'bands vertical rhythm space between sections',
    'appearance.keywords.radius' => 'corners rounded round',
    'appearance.keywords.border_width' => 'lines borders outline',
    'appearance.keywords.shadow' => 'shadows depth elevation',
    'appearance.keywords.container' => 'content width measure max width',
    'appearance.keywords.boxed' => 'sheet frame boxed page',
    'appearance.keywords.page_background' => 'backdrop outside',
    'appearance.keywords.header_arrangement' => 'logo menu layout nav navigation',
    'appearance.keywords.brand' => 'logo site name wordmark',
    'appearance.keywords.logo_size' => 'logo',
    'appearance.keywords.header_behaviour' => 'sticky fixed transparent over the hero scroll',
    'appearance.keywords.header_opacity' => 'transparent see-through',
    'appearance.keywords.header_blur' => 'frosted glass',
    'appearance.keywords.nav_style' => 'menu links navigation pills',
    'appearance.keywords.header_button' => 'call to action button cta',
    'appearance.keywords.footer_layout' => 'columns footer',
    'appearance.keywords.small_print_row' => 'copyright legal',
    'appearance.keywords.button_style' => 'buttons outline filled ghost',
    'appearance.keywords.button_radius' => 'buttons corners pill rounded',
    'appearance.keywords.button_height' => 'buttons size tall',
    'appearance.keywords.button_caps' => 'buttons uppercase capitals',
    'appearance.regions_hint' => 'Click any part of the page for its settings',
    'appearance.history' => 'Undo and redo',
    'appearance.undo' => 'Undo (⌘Z)',
    'appearance.redo' => 'Redo (⇧⌘Z)',
    'appearance.state.changes_one' => '1 unpublished change',
    'appearance.state.changes_many' => ':count unpublished changes',
    'appearance.state.problem' => 'Fix the contrast to publish',
    'appearance.revert' => 'Discard changes',

    // Designs the owner keeps (D-061).
    'appearance.library' => 'Your designs',
    'appearance.library.empty' => 'Nothing kept yet. Set the screen the way you want it and save it under a name.',
    'appearance.library.name' => 'Name for this design',
    'appearance.library.save' => 'Keep this design',
    'appearance.library.use' => 'Use this design',
    'appearance.library.delete' => 'Delete',
    'appearance.library.delete_one' => 'Delete “:name”',
    'appearance.library.saved' => 'Kept as “:name”. The site has not changed — press Publish for that.',
    'appearance.library.overwritten' => '“:name” now holds what is on this screen. The site has not changed.',
    'appearance.library.loaded' => '“:name” is on the screen. Press Publish to put it on the site.',
    'appearance.library.deleted' => '“:name” is gone. What is on the screen is untouched.',
    'appearance.library.name_needed' => 'Give the design a name first.',
    'appearance.library.from' => 'From :character',
    'appearance.library.by_hand' => 'Made by hand',

    // A design as a file, out and in (PLAN.md D-152).
    'appearance.export' => 'Export this design',
    'appearance.export_one' => 'Export “:name” as a file',
    'appearance.export_name' => 'My design',
    'appearance.export_missing' => 'That design is not here any more.',
    'appearance.import' => 'Import design…',
    'appearance.import_label' => 'A Boxlet design file (.json), exported from this site or another.',
    'appearance.import_send' => 'Import',
    'appearance.import_no_file' => 'Choose a design file first.',
    'appearance.import_refused' => 'This design can’t be imported: :reason',
    'appearance.import_question' => 'Import “:name”?',
    'appearance.import_warnings' => 'Left out on the way in:',
    'appearance.import_design_only' => 'This file is a design without a composition: it can be loaded into the screen, but not added as a character.',
    'appearance.import_add' => 'Add as character',
    'appearance.import_add_hint' => 'Kept among the characters, to load whenever you like. Nothing on the site changes.',
    'appearance.import_load' => 'Load into the screen',
    'appearance.import_load_hint' => 'Fills the screen with it, as loading a character does. Nothing changes on the site until you publish.',
    'appearance.import_cancel' => 'Cancel',
    'appearance.import_added' => '“:name” was added as a character.',
    'appearance.import_loaded' => '“:name” is loaded into the screen. Nothing changes on the site until you publish.',
    'appearance.import_gone' => 'There is no design waiting to be imported any more. Import the file again.',
    'appearance.custom' => 'Custom',
    'appearance.character_delete' => 'Delete character “:name”',
    'appearance.character_delete_confirm' => 'Delete the character “:name”? Pages keep their sections. A site composed with it composes with Minimal from now on.',
    'appearance.character_deleted' => 'The character “:name” is deleted.',
    'appearance.skipped' => 'These design files were left out. The site goes on without them:',
    'appearance.character_missing' => 'The character “:name” this site was composed with is gone. New blocks compose with :default until you choose another.',
];
