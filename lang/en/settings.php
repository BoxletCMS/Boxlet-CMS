<?php

// Site settings (PLAN.md D-028). The maintenance strings stay in update.php, where the
// rest of D-021's vocabulary lives — only the switch moved screens, not its words.

return [
    'settings.title' => 'Site settings',
    'settings.intro' => 'What this site is called, the time it keeps, the pictures that stand for it, and what visitors see while it is closed for maintenance.',

    'settings.site_name' => 'Site name',
    'settings.site_name_hint' => 'The site’s own name. It is shown at the top of this admin, and used where a page needs to name the site it belongs to. Left empty, the admin says Boxlet.',

    'settings.timezone' => 'Time zone',
    'settings.credit' => 'Show “Made with Boxlet” in the site\'s footer',
    'settings.credit_hint' => 'One quiet line in the small print, linking to boxlet.org. Off unless you want it: what your visitors read is yours, not Boxlet\'s.',
    'settings.timezone_hint' => 'The time zone your site lives in. Dates in the admin — when a page was last edited, when a picture was added — are shown in this zone.',
    'settings.timezone_invalid' => 'That is not a time zone this server knows. Nothing was saved.',

    'settings.branding' => 'Branding',
    'settings.general' => 'General',
    'settings.sections' => 'Settings sections',
    'settings.last_saved' => 'Last saved :when',
    'settings.branding_intro' => 'The pictures that stand for your site: in its header, on a browser tab, and when someone shares a link to it.',
    'settings.logo' => 'Logo',
    'settings.logo_hint' => 'Shown at the top left of every page, linking to the home page. It keeps its own shape — a wide logo stays wide — and its size is chosen under Design → Header and footer. A PNG or SVG-like picture with a transparent background works best.',
    'settings.logo_dark' => 'Logo for dark surfaces',
    'settings.logo_dark_hint' => 'Optional. A version of the logo for a dark header — a contrast surface, a gradient, or a header laid over a dark first section. Shown there instead of the logo above; leave it empty if the logo reads on both.',
    'settings.favicon' => 'Favicon',
    'settings.favicon_hint' => 'The small icon a browser shows on the tab and in bookmarks, and a phone on its home screen. Use a square picture; it is shown at 200×200 pixels and smaller.',
    'settings.share_image' => 'Default sharing image',
    'settings.share_image_hint' => 'The picture shown when someone shares a link to your site on social media or in a chat app. A landscape picture around 1200×630 pixels works best.',

    // No settings.maintenance heading: it said exactly what maintenance.title in update.php
    // already says, and that one lost its last caller when the switch left the dashboard.
    // Two keys with one wording is how they drift into two different words.
    'settings.maintenance_intro' => 'Maintenance mode closes the site to visitors while you work on it: they see a short message instead of your pages, and search engines are told to come back later. You still see the whole site while logged in.',
    'settings.maintenance_message' => 'Message for visitors while the site is closed',
    'settings.maintenance_message_hint' => 'What visitors read instead of your site while maintenance mode is on, for example “We are updating the site and will be back this afternoon.” Leave it empty for the standard wording shown in grey.',
    'settings.maintenance_message_save' => 'Save message',
    'settings.maintenance_message_saved' => 'The maintenance message was saved.',

    'settings.save' => 'Save settings',
    'settings.saved' => 'Settings saved.',
    'settings.picture_gone' => 'One of the pictures chosen is no longer in the library, so that field was cleared.',
];
