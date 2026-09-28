<?php

// Admin and installer UI strings. Every string shown in the admin comes from here via
// t('key'); :name placeholders are replaced by t()'s second argument.
return [
    'csrf.invalid' => 'This form has expired. Go back, reload the page and try again.',
    // Not a CSRF failure, though it arrives looking like one: PHP discards a post over
    // post_max_size whole, token and all. Saying the form expired would send someone to
    // reload and send the same oversized file again.
    'post.too_large' => 'That was larger than this server accepts (:limit per request), so none of it arrived. Nothing was saved.',

    'auth.title' => 'Log in',
    'auth.email' => 'Email',
    'auth.password' => 'Password',
    'auth.submit' => 'Log in',
    'auth.failed' => 'The email or password is incorrect.',
    'auth.throttled' => 'Too many login attempts. Try again in :minutes minutes.',

    'admin.brand' => 'Boxlet',
    'admin.skip' => 'Skip to content',
    'admin.nav.label' => 'Admin navigation',
    'admin.nav.dashboard' => 'Overview',
    // The rail's groups (D-052).
    'admin.nav.group.site' => 'Site',
    'admin.nav.group.content' => 'Content',
    'admin.nav.group.presentation' => 'Presentation',
    'admin.nav.group.administration' => 'Administration',
    'admin.your_login' => 'Your login',
    'admin.rail.fold' => 'Fold the menu to its icons',
    'admin.rail.open' => 'Open the menu',
    'admin.site_time' => 'The site\'s time zone, and the time there now',
    // The theme switch in the strip (D-054). The hint answers the fear the control raises:
    // a button in the admin that changes colours could be changing the site's.
    'admin.appearance' => 'Appearance',
    'admin.appearance_light' => 'Light',
    'admin.appearance_dark' => 'Dark',
    'admin.appearance_system' => 'Match the system',
    'admin.appearance_hint' => 'Light or dark. Only changes how the admin looks to you, never the site.',
    'admin.nav.pages' => 'Pages',
    'admin.nav.media' => 'Media',
    'admin.nav.appearance' => 'Appearance',
    'admin.nav.menus' => 'Menus',
    'admin.nav.forms' => 'Forms',
    'admin.nav.statistics' => 'Statistics',
    'admin.nav.design_style' => 'Character and colours',
    'admin.nav.open' => 'Menu',
    'admin.nav.settings' => 'Settings',
    'admin.nav.redirects' => 'Redirects',
    'admin.logout' => 'Log out',
    'admin.view_site' => 'View site',

    'admin.dashboard.maintenance' => 'Maintenance mode is on: visitors see a short message instead of the site.',
    'admin.dashboard.maintenance_link' => 'Switch it off in Settings',

    'richtext.toolbar' => 'Text formatting',
    'richtext.bold' => 'Bold',
    'richtext.italic' => 'Italic',
    'richtext.link' => 'Link',
    'richtext.unlink' => 'Unlink',
    'richtext.url' => 'Address',
    'richtext.url_placeholder' => 'https://example.com, an email or a phone number',
    'richtext.page' => 'Page',
    'richtext.heading_2' => 'Heading 2',
    'richtext.heading_3' => 'Heading 3',
    'richtext.heading_4' => 'Heading 4',
    'richtext.quote' => 'Quotation',
    'richtext.bullets' => 'Bulleted list',
    'richtext.numbers' => 'Numbered list',
    'richtext.undo' => 'Undo',
    'richtext.redo' => 'Redo',
    'richtext.plain' => 'Edit as HTML',
    'richtext.rich' => 'Edit as rich text',
    'richtext.paste_plain' => 'Ctrl+Shift+V pastes without formatting.',
];
