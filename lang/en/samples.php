<?php

// The copy a new block starts with, in English (PLAN.md D-176, BlockField's 'sample'). A
// visitor's words once the block is on a page, so it lives with the site's own words and is
// read in the page's language by site_t(…, 'samples'), English where a language has none.
//
// The register is deliberate (D-083): each line says what the part is for rather than
// pretending to be a real page, so the owner replaces it rather than judging it.
return [
    'preview.hero.heading' => 'The line that says what this is',
    'preview.hero.subheading' => 'A sentence under it, for what the line leaves out.',
    'preview.hero.cta' => 'The one thing to do',
    'preview.text.heading' => 'A section heading',
    'preview.text.body' => 'A paragraph or two, set the way this site sets writing.',
    'preview.image_text.heading' => 'A heading beside the picture',
    'preview.image_text.body' => 'What the picture is about, in a paragraph.',
    'preview.image_text.link' => 'Read more',
    'preview.cards.heading' => 'Three of something',
    'preview.picture.caption' => 'The workshop, on a Tuesday',
    'preview.quote.quote' => 'They rebuilt the shop in three weeks and it still feels like ours.',
    'preview.quote.attribution' => 'Ana Perić',
    'preview.quote.role' => 'The Bindery',
    'preview.gallery.heading' => 'A few pictures',
    'preview.gallery.caption' => 'What this one is',
    'preview.accordion.heading' => 'Things people ask',
    'preview.accordion.question' => 'How long does it take?',
    'preview.accordion.answer' => 'The answer, in a line or two.',
    'preview.cta.heading' => 'The thing you want them to do',
    'preview.cta.body' => 'One line of why they should.',
    'preview.cta.action' => 'Do it',
    'preview.cta.second' => 'Or read more',
    'preview.stats.heading' => 'In numbers',
    'preview.stats.value' => '12',
    'preview.stats.label' => 'what it counts',
    'preview.logos.heading' => 'Who we work with',
    'preview.logos.name' => 'A client',
    'preview.logos.link' => 'Their site',
    'preview.downloads.heading' => 'Take it with you',
    'preview.downloads.title' => 'Price list',
    'preview.downloads.description' => 'Every service and what it costs, on one page.',
    'preview.embed.caption' => 'What is in the frame',
    'preview.cards.intro' => 'A line above the three, if they need one.',
    'preview.cards.item_heading' => 'One of the three',
    'preview.cards.item_body' => 'A line or two about it.',
    'preview.cards.item_link' => 'More',
    'preview.form.heading' => 'Get in touch',
    'preview.form.intro' => 'A line telling people what happens when they write.',

    // What a repeater's "+" adds (D-179): new, and nothing more, so it reads right as the fourth.
    'item.cards.heading' => 'New card',
    'item.cards.body' => 'A short description.',
    'item.accordion.question' => 'New question',
    'item.accordion.answer' => 'The answer.',
    'item.stats.value' => '0',
    'item.stats.label' => 'New number',
    'item.logos.name' => 'New logo',
    'item.downloads.title' => 'New file',
    'item.downloads.description' => 'A short description.',
];
