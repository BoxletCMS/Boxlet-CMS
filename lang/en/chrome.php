<?php

// The site's header and footer (PLAN.md D-028, D-030). A new concern, so a new file:
// t() merges every file in the locale's directory, and a key defined twice is a test
// failure rather than a silent winner.
//
// Written IN THE SAME BREATH as views/chrome.php. Calling t() for a key that does not
// exist yet put a bare key on a screen in 5a, and an untranslated aria-label on the front
// end earlier today; the order is what prevents it, not care.

return [
    'chrome.title' => 'Header and footer',
    'chrome.intro' => 'What sits at the top and bottom of every page. Set once for the whole site.',

    'chrome.shared' => 'The same in every language',
    'chrome.words' => 'Words',
    // Each tab holds its own half of the words (D-111), folded per language when there are several.
    'chrome.words.header' => 'Header words',
    'chrome.words.footer' => 'Footer words',


    'chrome.logo_where' => 'The logo is set with the site’s other pictures, under',
    'chrome.logo_where_link' => 'Settings → Branding.',
    'chrome.menu' => 'Menu',
    'chrome.menu_hint' => 'Which menu the header and footer show. Menus are built under Menus; a menu with the same name in each language gives every translation its own words.',
    'chrome.menu_none' => 'No menu',
    'chrome.no_menus' => 'There are no menus yet. Build one under Menus and it will be offered here.',
    'chrome.menu_gone' => 'The menu you had chosen no longer exists, so it was cleared.',

    // "Button" alone read as a toggle or a section heading on the rendered screen, with
    // "Button address" directly under it. It is the words ON the button, so it says so.
    'chrome.button_label' => 'Button text',
    'chrome.button_label_hint' => 'The words on the button, such as “Get in touch”. Choosing a page fills in its title; change it if you like. Without text no button is shown.',
    'chrome.button_url' => 'Button links to',
    'chrome.button_url_hint' => 'An optional button at the right of the header, for the one thing you most want visitors to do. Choose one of your pages, or “Another address…” to link anywhere else. Leave both empty for no button.',
    'chrome.button_address' => 'Button address',
    'chrome.button_address_hint' => 'Filled in when you choose a page. For anything else type it here: /contact, https://example.com, an email address or a phone number. An email opens the visitor’s mail app; a phone number can be tapped to call on a phone.',
    'chrome.button_url_refused' => 'That address was not accepted, so it was not saved.',

    'chrome.text' => 'Words',
    'chrome.text_hint' => 'A line or two — who you are, where to find you. Bold, italic and links; an email address or a phone number as a link opens the visitor’s mail app or dialler.',
    'chrome.small_print' => 'Small print',
    'chrome.small_print_hint' => 'The very last line of every page: a copyright, a company number, whatever the law asks for.',

    'chrome.look' => 'How they look',
    'chrome.look_intro' => 'Each choice follows the character until you pick something else. Colours come from the palette, so every combination stays readable.',
    // On the Appearance screen the follow state is a segment and a badge (D-065): the
    // segment names what the character gives, the badge says the choice is still its.
    'chrome.look.follow_short' => 'Follow: :value',
    'chrome.look.following' => 'following',
    'chrome.look.follow' => 'As the character has it: :value',
    // Arrangement and behaviour are two choices since D-112; the old header_layout values
    // are read as pairs of them (ChromeLook::LEGACY).
    'chrome.look.header_arrangement' => 'Header arrangement',
    'chrome.look.header_arrangement.left' => 'Left',
    'chrome.look.header_arrangement.inline' => 'Inline',
    'chrome.look.header_arrangement.centred' => 'Centred',
    'chrome.look.header_arrangement.split' => 'Split',
    'chrome.look.header_arrangement.masthead' => 'Masthead',
    'chrome.look.header_behaviour' => 'Header behaviour',
    'chrome.look.header_behaviour.static' => 'Stays put',
    'chrome.look.header_behaviour.sticky' => 'Sticky',
    'chrome.look.header_behaviour.over' => 'Over the top',
    // The footer's columns (D-115).
    'chrome.footer_column' => 'Column :n',
    'chrome.footer_column_title' => 'Title',
    'chrome.footer_column_menu' => 'Column :n menu',
    'chrome.footer_menu_same' => 'The header’s menu',
    'chrome.footer_menu_hint' => 'None, the header’s menu again, or a menu of this column’s own — Privacy, Imprint, Terms. Built under Menus, chosen by name, so each language’s menu of that name is the column’s. Its title and words are under the footer’s words below.',
    'chrome.footer_column_menu_hint' => 'Drawn only when the arrangement shows this column. Its title and words are under the footer’s words below.',
    'chrome.look.footer_layout' => 'Footer arrangement',
    'chrome.look.footer_layout.simple' => 'One column',
    'chrome.look.footer_layout.centred' => 'Centred',
    'chrome.look.footer_layout.columns' => 'Two columns',
    'chrome.look.footer_layout.menu_first' => 'Menu first',
    'chrome.look.footer_layout.three' => 'Three columns',
    'chrome.look.footer_edge' => 'Footer edge',
    'chrome.look.footer_edge.none' => 'None',
    'chrome.look.footer_edge.line' => 'Line',
    'chrome.look.footer_edge.slant' => 'Slant',
    'chrome.look.footer_edge.curve' => 'Curve',
    'chrome.look.small_print_row' => 'Last row',
    'chrome.look.small_print_row.left' => 'Stacked',
    'chrome.look.small_print_row.split' => 'Split',
    'chrome.look.small_print_row.centred' => 'Centred',
    'chrome.look.header_surface' => 'Header surface',
    'chrome.look.header_surface.plain' => 'Plain',
    'chrome.look.header_surface.tinted' => 'Tinted',
    'chrome.look.header_surface.contrast' => 'Contrast',
    'chrome.look.header_surface.gradient' => 'Gradient',
    'chrome.look.footer_surface' => 'Footer surface',
    'chrome.look.footer_surface.plain' => 'Plain',
    'chrome.look.footer_surface.tinted' => 'Tinted',
    'chrome.look.footer_surface.contrast' => 'Contrast',
    'chrome.look.footer_surface.gradient' => 'Gradient',
    'chrome.look.density' => 'Density',
    'chrome.look.density.compact' => 'Compact',
    'chrome.look.density.normal' => 'Normal',
    'chrome.look.density.roomy' => 'Roomy',
    'chrome.look.header_edge' => 'Header edge',
    'chrome.look.header_edge.none' => 'None',
    'chrome.look.header_edge.line' => 'Line',
    'chrome.look.header_edge.shadow' => 'Shadow',
    'chrome.look.brand' => 'Site name',
    'chrome.look.brand.logo' => 'Logo',
    'chrome.look.brand.name' => 'Name',
    'chrome.look.brand.both' => 'Both',
    'chrome.look.nav_style' => 'Menu style',
    'chrome.look.nav_style.plain' => 'Plain',
    'chrome.look.nav_style.caps' => 'Capitals',
    'chrome.look.nav_style.pills' => 'Pills',
    'chrome.look.nav_style.bar' => 'Bar',
    'chrome.look.nav_style.chips' => 'Chips',
    'chrome.look.nav_ink' => 'Menu colour',
    'chrome.look.nav_ink.accent' => 'Accent',
    'chrome.look.nav_ink.ink' => 'Text',
    'chrome.look.header_button' => 'Button style',
    'chrome.look.header_button.filled' => 'Filled',
    'chrome.look.header_button.outline' => 'Outline',
    'chrome.look.header_button.text' => 'Link',
    // Renamed (D-116's third round): "Footer menu columns: Three" read as the footer's three
    // columns, and the owner set it looking for them. It is how a long menu is listed.
    'chrome.look.footer_columns' => 'Long menu',
    'chrome.look.footer_columns.2' => 'One list',
    'chrome.look.footer_columns.3' => 'Two lists',
    'chrome.look.footer_columns.4' => 'Three lists',
    'chrome.look.logo_size' => 'Logo size',
    'chrome.look.logo_size.small' => 'Small',
    'chrome.look.logo_size.medium' => 'Medium',
    'chrome.look.logo_size.large' => 'Large',

    'chrome.save' => 'Save header and footer',
    'chrome.saved' => 'Header and footer saved.',
];
