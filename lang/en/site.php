<?php

// What the site itself says to a visitor, in English (PLAN.md O-19, D-044). Everything
// else a visitor reads is the owner's own words.
return [
    'site.not_found.title' => 'Page not found',
    'site.not_found.intro' => 'There is no page at this address.',
    'site.languages' => 'Languages',
    'site.menu' => 'Menu',
    'site.credit' => 'Made with Boxlet CMS',
    'site.enlarge.open' => 'Open larger',
    'site.enlarge.close' => 'Close',
    'site.enlarge.previous' => 'Previous picture',
    'site.enlarge.next' => 'Next picture',
    'site.enlarge.count' => ':n of :total',
    'site.form.send' => 'Send',
    'site.form.thanks' => 'Thank you, your message has been sent.',
    'site.form.required' => 'Please fill in this field.',
    'site.form.email' => 'Please enter a valid email address.',
    'site.form.choose' => 'Choose…',
    'site.form.error' => 'Please correct the fields marked below.',
    'site.form.too_many' => 'Too many messages from here in a short time. Please try again later.',
    'site.form.not_sent' => 'Your message could not be sent just now. Please try again in a little while.',
    'site.form.optional' => '(optional)',
    'site.form.field_name' => 'Name',
    'site.form.field_email' => 'Email',
    'site.form.field_message' => 'Message',
    // What an embedded frame is called when the owner gave it no caption. A frame with no
    // title is announced as "frame" and nothing else, which tells a screen reader's user
    // only that something is in their way.
    'site.embed.youtube' => 'Video',
    'site.embed.vimeo' => 'Video',
    'site.embed.openstreetmap' => 'Map',
    'site.embed.googlemaps' => 'Map',
    // Drawn only in the editor's canvas (blocks.css hides it on the page): the visitor
    // cannot fix the address, and the owner is looking at it the moment they paste one.
    'site.embed.play' => 'Play video',
    'site.embed.show_map' => 'Show map',
    'site.embed.unknown' => 'Boxlet can show a video from YouTube or Vimeo, and a map from OpenStreetMap or Google Maps. That address is not one of them.',
    // The page a visitor gets while the site is closed (D-019, D-021), in the site's main
    // language (D-241): the owner's own message, when there is one, instead of maintenance.body.
    'site.closed.maintenance.title' => 'Back in a moment',
    'site.closed.maintenance.body' => 'This site is briefly unavailable. Please try again shortly.',
    'site.closed.update.title' => 'Being updated',
    'site.closed.update.body' => 'This site is being updated and will be back in a moment.',

    // The box that asks about the owner's scripts' cookies (PLAN.md O-73).
    'site.consent.title' => 'Cookies',
    'site.consent.text' => 'With your consent, this site uses cookies and similar storage beyond what it needs to work. You choose what to allow, and can change it at any time under Cookie settings at the bottom of the page.',
    'site.consent.accept' => 'Accept all',
    'site.consent.reject' => 'Reject all',
    'site.consent.more' => 'Choose what to allow',
    'site.consent.save' => 'Save my choice',
    'site.consent.settings' => 'Cookie settings',
    'site.consent.always' => 'Always on',
    'site.consent.kind.necessary' => 'Necessary',
    'site.consent.kind.necessary_text' => 'What the site needs to work, and to remember this choice. It cannot be turned off.',
    'site.consent.kind.statistics' => 'Statistics',
    'site.consent.kind.statistics_text' => 'Counts visits and how the site is used, so it can be improved.',
    'site.consent.kind.marketing' => 'Marketing',
    'site.consent.kind.marketing_text' => 'Lets advertising services recognise you and show you their ads.',
    'site.consent.cookies' => 'Cookies and storage',
    'site.consent.none_listed' => 'None listed.',
    'site.consent.this_site' => 'This site',
    'site.consent.remembers' => 'Remembers your choice about cookies',
    'site.consent.until_cleared' => 'Until you clear it',
    // What a known service's cookies are for, and how long they stay (ScriptCatalogue, O-73).
    'site.consent.catalogue.visitor' => 'Tells visitors apart',
    'site.consent.catalogue.session' => 'Keeps one visit together',
    'site.consent.catalogue.ads' => 'Shows you ads on other sites',
    'site.consent.catalogue.2y' => '2 years',
    'site.consent.catalogue.13m' => '13 months',
    'site.consent.catalogue.1y' => '1 year',
    'site.consent.catalogue.90d' => '90 days',
    'site.consent.catalogue.1d' => '1 day',
    'site.consent.catalogue.30min' => '30 minutes',
];
