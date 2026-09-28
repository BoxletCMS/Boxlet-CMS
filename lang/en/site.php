<?php

// What the site itself says to a visitor, in English (PLAN.md O-19, D-044). Everything
// else a visitor reads is the owner's own words.
return [
    'site.not_found.title' => 'Page not found',
    'site.not_found.intro' => 'There is no page at this address.',
    'site.languages' => 'Languages',
    'site.menu' => 'Menu',
    'site.credit' => 'Made with Boxlet',
    'site.form.send' => 'Send',
    'site.form.thanks' => 'Thank you, your message has been sent.',
    'site.form.required' => 'Please fill in this field.',
    'site.form.email' => 'Please enter a valid email address.',
    'site.form.choose' => 'Choose…',
    'site.form.error' => 'Please correct the fields marked below.',
    'site.form.too_many' => 'Too many messages from here in a short time. Please try again later.',
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
    'site.embed.unknown' => 'Boxlet can show a video from YouTube or Vimeo, and a map from OpenStreetMap or Google Maps. That address is not one of them.',
];
