<?php

// Demo site content. Between them the pages use every block type, every layout, and
// every value of every section style (tests/demo_test.php checks this). The copy is
// deliberately plain so that any character looks at home on it.
// Each block: [type, content, section style, layout].
//
// NO MEDIA IDS HERE. A media field is left out, which normalises to null: an id for a
// picture that was never uploaded is a dangling reference, and the first photograph
// that happens to take that number is silently adopted by the page. Photographs arrive
// with D-022 and are set explicitly then.
//
// THE CONTACT FORM IS WRITTEN `demo:form`: the seed makes one form in the demo's language
// and puts its id there (PLAN.md D-046).
//
// LINKS TO DEMO PAGES ARE WRITTEN `demo:{slug}` (`demo:` alone is the home page). The seed
// turns each into a page reference, `page:{group}`, once every page exists — the demo links
// the way an owner's site does (PLAN.md D-034), so renaming a page cannot break it.

return [
    [
        'slug' => '',
        'title' => 'Northwind Studio',
        'blocks' => [
            ['hero', [
                'heading' => 'Small studio, carefully made websites',
                'subheading' => 'We design and build sites for independent shops, practices and makers.',
                'cta' => ['label' => 'See what we do', 'url' => 'demo:services'],
            ], ['surface' => 'gradient', 'rhythm' => 'airy', 'align' => 'center'], 'center'],
            ['columns', [
                'heading' => 'What we do',
                'intro' => 'Three things, done properly, for a handful of clients at a time.',
                'items' => [
                    ['heading' => 'Design', 'body' => '<p>A character for your site, chosen once and applied everywhere.</p>', 'link' => ['label' => 'How we design', 'url' => 'demo:about']],
                    ['heading' => 'Build', 'body' => '<p>Pages you can edit yourself, in every language you need.</p>', 'link' => ['label' => 'What we build', 'url' => 'demo:services']],
                    ['heading' => 'Care', 'body' => '<p>Updates, backups and small changes, every month, for a fixed fee.</p>', 'link' => ['label' => 'Care plans', 'url' => 'demo:services']],
                ],
            ], [], 'three'],
            ['image_text', [
                'heading' => 'Design first, then everything else',
                'body' => '<p>Every project starts with how the site should feel: calm and editorial, loud and confident, or somewhere in between.</p><p>The pages, the colours and the type all follow from those few decisions.</p>',
                'link' => ['label' => 'How we work', 'url' => 'demo:about'],
            ], ['surface' => 'tinted'], 'image-left'],
            ['quote', [
                'quote' => 'They understood what we wanted before we could put it into words.',
                'attribution' => 'Ana Marić',
                'role' => 'Owner, Marić Bakery',
            ], ['width' => 'narrow', 'align' => 'center', 'divider' => 'line'], 'card'],
            ['image_text', [
                'heading' => 'Built to be looked after',
                'body' => '<p>You edit your own pages. Nothing breaks when you do, because every block already knows how to look good.</p>',
                'image_fit' => 'contain',
            ], [], 'image-right'],
            ['logos', [
                'heading' => 'Who we work with',
                'items' => [
                    ['name' => 'Marić Bakery'],
                    ['name' => 'Dr Babić Practice'],
                    ['name' => 'Sjever Bindery'],
                    ['name' => 'Ilica Flowers'],
                    ['name' => 'Kovač Joinery'],
                ],
            ], ['align' => 'center'], 'row'],
            ['form', [
                'heading' => 'Write to us',
                'intro' => 'Tell us a little about your project. We reply within two working days.',
                'form' => 'demo:form',
            ], ['surface' => 'tinted'], 'stacked'],
            // A Hero stood here until D-105, which is exactly the misuse the CTA block was
            // written for: a block that opens a page is the wrong weight for one that closes it.
            ['cta', [
                'heading' => 'Ready when you are',
                'body' => 'Tell us about your project and we will reply within two working days.',
                'action' => ['label' => 'Get in touch', 'url' => 'mailto:hello@example.com'],
                'second' => ['label' => 'See what we do', 'url' => 'demo:services'],
            ], ['surface' => 'contrast', 'divider' => 'slant'], 'banner'],
        ],
    ],
    [
        'slug' => 'about',
        'title' => 'About',
        'blocks' => [
            ['hero', [
                'heading' => 'About Northwind',
                'subheading' => 'Three people, one room, and a strong opinion about typography.',
            ], ['rhythm' => 'tight'], 'left'],
            ['text', [
                'heading' => 'How we work',
                'body' => '<p>We start by listening. Before anything is drawn, we want to know who visits your site and what they came for.</p><h3>Then we decide</h3><p>Colour, type, space and shape are chosen once, together, and applied everywhere. That is what keeps a site coherent as it grows.</p><ul><li>One conversation about character</li><li>A handful of real decisions</li><li>Pages you can edit yourself</li></ul>',
            ], ['width' => 'wide'], 'columns'],
            ['stats', [
                'heading' => 'Northwind in numbers',
                'items' => [
                    ['value' => '12', 'label' => 'years doing this'],
                    ['value' => '80+', 'label' => 'sites built'],
                    ['value' => '2 days', 'label' => 'to answer you'],
                ],
            ], ['rhythm' => 'tight'], 'three'],
            ['columns', [
                'heading' => 'The people',
                'items' => [
                    ['heading' => 'Ana Horvat', 'body' => '<p>Design and typography. Decides how it feels.</p>'],
                    ['heading' => 'Marko Kovač', 'body' => '<p>Builds it, and keeps it running.</p>'],
                ],
                'image_shape' => 'round',
            ], ['surface' => 'tinted', 'align' => 'center'], 'two'],
            ['image_text', [
                'heading' => 'A quiet workshop',
                'body' => '<p>We keep the team small on purpose. You always talk to the people doing the work.</p>',
                'link' => ['label' => 'Our services', 'url' => 'demo:services'],
            ], ['surface' => 'contrast', 'divider' => 'line'], 'image-right'],
            ['quote', [
                'quote' => 'We asked for a site we could look after ourselves. Two years on, we still have not needed to call anybody.',
                'attribution' => 'Petar Babić',
                'role' => 'Dr Babić Practice',
            ], ['width' => 'narrow'], 'plain'],
            ['form', [
                'heading' => 'Ask us anything',
                'intro' => 'Questions about a project, a price or a date. One of us will answer, usually the same day.',
                'form' => 'demo:form',
            ], [], 'beside'],
            ['text', [
                'heading' => 'Where to find us',
                'body' => '<p>Ilica 1, Zagreb. Coffee is on us. Write to <a href="mailto:hello@example.com">hello@example.com</a> first.</p>',
            ], ['surface' => 'tinted', 'width' => 'narrow', 'align' => 'center', 'divider' => 'curve'], 'single'],
            // A rule between the address and the map: two separate thoughts in one place.
            ['divider', ['height' => 'medium'], ['surface' => 'tinted', 'rhythm' => 'tight'], 'line'],
            // The one embed on a page that is not a catalogue, because this is what an embed
            // is FOR: the address above, shown. OpenStreetMap rather than Google, so a
            // visitor reading a studio's contact page is not handed a tracker to do it.
            ['embed', [
                'url' => 'https://www.openstreetmap.org/#map=16/45.8131/15.9775',
                'caption' => 'Ilica 1, Zagreb',
                'ratio' => 'square',
            ], ['surface' => 'tinted', 'width' => 'narrow'], 'full'],
        ],
    ],
    [
        'slug' => 'services',
        'title' => 'Services',
        'blocks' => [
            ['hero', [
                'heading' => 'What we do',
                'subheading' => 'Design, build and care for small websites that are easy to run.',
                'cta' => ['label' => 'Start a project', 'url' => 'mailto:hello@example.com'],
            ], ['surface' => 'tinted', 'rhythm' => 'airy'], 'split'],
            ['image_text', [
                'heading' => 'New sites',
                'body' => '<p>From a single page to a few dozen, in one or more languages. Designed around your content, not a theme.</p>',
            ], [], 'image-left'],
            ['image_text', [
                'heading' => 'Redesigns',
                'body' => '<p>We keep what works, move your content across, and give the whole site one consistent character.</p>',
            ], ['surface' => 'tinted', 'divider' => 'slant'], 'image-right'],
            ['columns', [
                'heading' => 'How a project runs',
                'intro' => 'Four steps, usually six to ten weeks from the first conversation to launch.',
                'items' => [
                    ['heading' => '1. Listen', 'body' => '<p>Who visits, and what they came for.</p>'],
                    ['heading' => '2. Decide', 'body' => '<p>Colour, type, space and shape, once.</p>'],
                    ['heading' => '3. Build', 'body' => '<p>Your pages, with your words in them.</p>'],
                    ['heading' => '4. Hand over', 'body' => '<p>You edit; we look after the rest.</p>'],
                ],
                'image_shape' => 'square',
            ], ['width' => 'wide'], 'four'],
            ['text', [
                'heading' => 'Care plans',
                'body' => '<p>Updates, backups and small changes every month, for a fixed fee. <strong>No surprises on the invoice.</strong></p>',
            ], ['surface' => 'gradient', 'width' => 'full', 'align' => 'center'], 'single'],
            ['accordion', [
                'heading' => 'Things people ask',
                'items' => [
                    ['question' => 'How long does a site take?', 'answer' => '<p>Six to ten weeks from the first conversation to launch, for most sites. A single page can be a fortnight.</p>'],
                    ['question' => 'What does it cost?', 'answer' => '<p>A small site starts around the price of a good second-hand car. We give a fixed number before anything is drawn, and it does not move.</p>'],
                    ['question' => 'Can I edit it myself?', 'answer' => '<p>Yes, and you are meant to. Every block already knows how to look right, so nothing breaks when you change the words.</p>'],
                    ['question' => 'What if I need another language?', 'answer' => '<p>Add it whenever you like. Pages link across languages, so a translation is never a second site.</p>'],
                ],
                'start' => 'first-open',
            ], ['width' => 'narrow'], 'list'],
            ['cta', [
                'heading' => 'Start with a conversation',
                'body' => 'Half an hour, no charge, and you will know whether we are the right people.',
                'action' => ['label' => 'Book a call', 'url' => 'mailto:hello@example.com'],
                'second' => ['label' => 'Read about us first', 'url' => 'demo:about'],
            ], ['surface' => 'contrast', 'divider' => 'curve'], 'beside'],
        ],
    ],
    [
        'slug' => 'style-guide',
        'title' => 'Style guide',
        'blocks' => [
            ['hero', [
                'heading' => 'Style guide',
                'subheading' => 'Every surface, rhythm, width and edge in one place. Switch the character and watch all of it change.',
            ], ['rhythm' => 'tight', 'align' => 'center'], 'center'],
            // rhythm is stated rather than inherited. The seed fills unnamed keys from the
            // character's composition now, and `minimal` composes `airy`, so without this
            // the demo never shows `normal` at all — and this page exists to show every
            // value of every choice.
            ['text', ['heading' => 'Plain surface, normal rhythm', 'body' => '<p>Body text with <strong>bold</strong>, <em>italic</em> and <a href="demo:">a link</a>.</p>'], ['rhythm' => 'normal'], 'single'],
            ['text', ['heading' => 'Tinted surface', 'body' => '<p>Tinted sections separate content without shouting.</p>'], ['surface' => 'tinted', 'divider' => 'line'], 'single'],
            ['text', ['heading' => 'Contrast surface', 'body' => '<p>Text and <a href="demo:">links</a> switch colour on contrast surfaces.</p>'], ['surface' => 'contrast', 'divider' => 'slant'], 'single'],
            ['text', ['heading' => 'Image surface', 'body' => '<p>Until a picture is chosen, the image surface uses the contrast colours.</p>'], ['surface' => 'image', 'divider' => 'curve'], 'single'],
            ['text', ['heading' => 'Gradient surface', 'body' => '<p>From the main colour to a neighbouring hue.</p>'], ['surface' => 'gradient'], 'single'],
            // The other alignment, said out loud for the same reason: `minimal` composes
            // `center`, so `left` appears nowhere unless a block asks for it.
            ['text', ['heading' => 'Left aligned', 'body' => '<p>Text ranged left, against the centred sections above it.</p>'], ['align' => 'left'], 'single'],
            ['text', ['heading' => 'Tight rhythm, wide', 'body' => '<p>Less space above and below, more across.</p>'], ['rhythm' => 'tight', 'width' => 'wide'], 'columns'],
            ['text', ['heading' => 'Airy rhythm, full width', 'body' => '<p>Room to breathe, edge to edge.</p>'], ['surface' => 'tinted', 'rhythm' => 'airy', 'width' => 'full', 'divider' => 'curve'], 'single'],
        ],
    ],

    // A FIFTH PAGE, WHICH IS A SHOWROOM AND SAYS SO. The four pages above are a studio's
    // site and the blocks on them are there because that page needed them; this one exists
    // to show every remaining shape once. Keeping the two apart is deliberate — a demo
    // where every page is a catalogue teaches nobody what a page looks like.
    //
    // The pictures are placeholders here and nowhere else. The seed references no media
    // (see the note at the top), so a Gallery on this page is the empty grid an owner sees
    // before they choose anything, which is the honest thing for a showroom to show.
    [
        'slug' => 'blocks',
        'title' => 'Blocks',
        'blocks' => [
            ['hero', [
                'heading' => 'Everything you can put on a page',
                'subheading' => 'One of each, in each of its shapes. Switch the character and every one of them changes with it.',
            ], ['rhythm' => 'tight'], 'center'],
            // THE HERO WITH A PICTURE BEHIND ITS WORDS (PLAN.md D-118), in its four
            // arrangements, each at another height and shade. With no picture chosen, which
            // is what a showroom without media has, the contrast colour stands in for one.
            // The band's own width, not full: the picture reaches the band's edges whatever
            // the width, and the width is what lines the words up with the rest of the page
            // — at full, they stood at the window's edge under a header that did not.
            ['text', ['heading' => 'A picture behind the words', 'body' => '<p>Four arrangements of the hero, each with a height and a shade of its own. Choose a picture and it fills the band.</p>'], ['width' => 'narrow'], 'single'],
            ['hero', [
                'heading' => 'Words in the middle',
                'subheading' => 'As tall as the words, with the lightest shade.',
                'cta' => ['label' => 'See what we do', 'url' => 'demo:services'],
                'height' => 'content',
                'veil' => 'light',
            ], ['rhythm' => 'airy'], 'cover-center'],
            ['hero', [
                'heading' => 'Words on the left',
                'subheading' => 'Tall, with a shade that clears away from the words.',
                'height' => 'tall',
                'veil' => 'medium',
            ], [], 'cover-left'],
            ['hero', [
                'heading' => 'Words on the right',
                'subheading' => 'Tall, the same shade turned the other way.',
                'height' => 'tall',
                'veil' => 'medium',
            ], [], 'cover-right'],
            ['hero', [
                'heading' => 'Words low on the left',
                'subheading' => 'Tall, with the strongest shade gathered under the words.',
                'cta' => ['label' => 'Start a project', 'url' => 'mailto:hello@example.com'],
                'height' => 'tall',
                'veil' => 'strong',
            ], [], 'cover-low'],
            // FILES TO TAKE AWAY (PLAN.md D-127), in both arrangements. The seed ships no
            // files, as it ships no pictures, so the items name none: on the page they are not
            // drawn, and the editor shows each as the place to choose one.
            ['downloads', ['heading' => 'Price lists and forms', 'items' => [
                ['title' => 'Price list', 'description' => 'Every service and what it costs.'],
                ['title' => 'Project questionnaire', 'description' => 'Fill it in before we meet.'],
            ]], ['width' => 'narrow'], 'list'],
            ['downloads', ['heading' => 'Brochures', 'items' => [
                ['title' => 'The studio', 'description' => 'Who we are, in four pages.'],
                ['title' => 'Our process', 'description' => 'From the first talk to going live.'],
            ]], [], 'cards'],
            ['text', ['heading' => 'A picture on its own', 'body' => '<p>Fills the column, or sits inset with room around it.</p>'], ['width' => 'narrow'], 'single'],
            ['picture', ['caption' => 'Filling the column', 'shape' => 'wide'], ['rhythm' => 'tight'], 'full'],
            ['picture', ['caption' => 'Inset, with room around it', 'shape' => 'wide'], ['surface' => 'tinted', 'rhythm' => 'tight'], 'inset'],
            // NAMED, because an unlabelled gap on a page of labelled blocks reads as a
            // mistake — it did, in the first screenshot of this page. And `tight`: a band's
            // own rhythm is air the spacer then adds to, which was a screenful of nothing.
            ['text', ['heading' => 'Room between two things', 'body' => '<p>Just space, or a line across it. Below is the space; the line is under the address on the About page.</p>'], ['width' => 'narrow'], 'single'],
            ['divider', ['height' => 'large'], ['rhythm' => 'tight'], 'space'],
            ['text', ['heading' => 'Several pictures together', 'body' => '<p>Two, three or four across. One crop for all of them, so the rows line up.</p>'], ['width' => 'narrow'], 'single'],
            ['gallery', [
                'heading' => 'Two across, as they are',
                'items' => [['caption' => 'The workshop'], ['caption' => 'The press']],
                'shape' => 'natural',
            ], ['rhythm' => 'tight'], 'two'],
            ['gallery', [
                'heading' => 'Three across, square',
                'items' => [['caption' => 'Setting'], ['caption' => 'Binding'], ['caption' => 'Finishing']],
                'shape' => 'square',
            ], ['surface' => 'tinted', 'rhythm' => 'tight'], 'three'],
            ['gallery', [
                'heading' => 'Four across, round',
                'items' => [['caption' => 'Ana'], ['caption' => 'Marko'], ['caption' => 'Petra'], ['caption' => 'Ivan']],
                'shape' => 'round',
            ], ['rhythm' => 'tight'], 'four'],
            ['stats', [
                'heading' => 'Four numbers',
                'items' => [
                    ['value' => '12', 'label' => 'years'],
                    ['value' => '80+', 'label' => 'sites'],
                    ['value' => '5', 'label' => 'languages'],
                    ['value' => '2 days', 'label' => 'to answer'],
                ],
            ], ['surface' => 'contrast', 'divider' => 'slant'], 'four'],
            ['accordion', [
                'heading' => 'Questions, as separate cards',
                'items' => [
                    ['question' => 'What is a block?', 'answer' => '<p>One thing on a page: a heading, a picture, a row of numbers. You add them, arrange them, and the design layer draws them.</p>'],
                    ['question' => 'What is a section?', 'answer' => '<p>The band a block stands in. It owns the background, the spacing and the width, and it can hold several blocks side by side.</p>'],
                ],
                'start' => 'closed',
            ], ['width' => 'narrow'], 'cards'],
            ['logos', [
                'heading' => 'Marks in an even grid',
                'items' => [
                    ['name' => 'Marić Bakery'],
                    ['name' => 'Dr Babić Practice'],
                    ['name' => 'Sjever Bindery'],
                    ['name' => 'Ilica Flowers'],
                    ['name' => 'Kovač Joinery'],
                    ['name' => 'Zagreb Type'],
                ],
            ], ['surface' => 'tinted', 'align' => 'center'], 'grid'],
            ['embed', [
                'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                'caption' => 'A video, inset',
                'ratio' => 'wide',
            ], ['width' => 'narrow', 'divider' => 'line'], 'inset'],
        ],
    ],
];
