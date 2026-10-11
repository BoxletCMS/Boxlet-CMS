<?php

/*
 * The Printworks: what's on (PLAN.md D-213). Three weeks side by side, each a heading and its
 * events; the sessions that come back every week; the newsletter. Boxlet has no events, so
 * each event is a card, its day counted from the install, so a new demo never opens on a past
 * programme.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d an event's day and time, this many days after the install
 * @param Closure(int): string $on that day's date alone
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    $event = static fn (int $days, string $time, string $hr, string $en, string $aboutHr, string $aboutEn): array => [
        'heading' => $t($hr, $en),
        'body' => '<p><strong>' . $d($days, $time) . '</strong></p>' . $p($aboutHr, $aboutEn),
    ];
    $week = static fn (int $n, array $events): array => [
        ['text', [
            'heading' => $t($n . '. tjedan', 'Week ' . $n),
            'body' => $p('Od ' . $on(($n - 1) * 7 + 1) . '.', 'From ' . $on(($n - 1) * 7 + 1) . '.'),
        ], 'single', [], $n - 1],
        ['cards', ['items' => $events], 'list', ['image_shape' => 'none'], $n - 1],
    ];

    return [
        'key' => 'program',
        'slug' => $t('program', 'whats-on'),
        'title' => $t('Program', 'What\'s on'),
        'description' => $t('Razgovori, projekcije, radionice, koncerti i sajmovi u sljedeća tri tjedna.', 'Talks, screenings, workshops, concerts and markets over the next three weeks.'),
        'menu' => 'main',
        'sections' => [
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['hero', [
                    'heading' => $t('Što je na programu', 'What\'s on'),
                    'subheading' => $t('Većina događaja je besplatna. Za radionice i koncerte prijavite se unaprijed.', 'Most events are free. Workshops and concerts are booked ahead.'),
                    'image' => 'demo-picture:space/rows-teal-chairs',
                ], 'left', ['height' => 'auto'], 0],
            ]],
            ['style' => ['name' => $t('Tri tjedna', 'Three weeks')], 'layout' => 'thirds', 'blocks' => array_merge(
                $week(1, [
                    $event(1, '19:00', 'Razgovor: Kako se reže ploča', 'Talk: Cutting the block', 'Grafičarka o drvu, nožu i strpljenju.', 'A printmaker on wood, knives and patience.'),
                    $event(3, '18:00', 'Linorez za početnike', 'Linocut for beginners', 'Tri sata, jedna ploča, deset otisaka.', 'Three hours, one block, ten prints.'),
                    $event(5, '20:30', 'Koncert u tiskarskoj dvorani', 'A concert in the press hall', 'Gudački kvartet među starim prešama.', 'A string quartet among the old presses.'),
                ]),
                $week(2, [
                    $event(8, '20:00', 'Projekcija: Ljudi od olova', 'Screening: People of Lead', 'Dokumentarac o posljednjim slagarima.', 'A documentary about the last typesetters.'),
                    $event(10, '17:30', 'Sitotisak: torbe', 'Screen printing: tote bags', 'Dizajnirajte i otisnite vlastitu torbu.', 'Design and print a bag of your own.'),
                ]),
                $week(3, [
                    $event(14, '10:00', 'Sajam papira i tiska', 'Paper & print market', 'Tridesetak izlagača, od papira do preša.', 'Thirty stalls, from paper to presses.'),
                    $event(16, '18:30', 'Razgovor: Slova na ulici', 'Talk: Letters in the street', 'Šetnja kvartom i natpisima koje više nitko ne slaže.', 'A walk past the signs nobody sets any more.'),
                    $event(18, '19:00', 'Večer za članove', 'Members\' evening', 'Pogled iza zatvorenih vrata nove izložbe.', 'A look behind the doors of the next exhibition.'),
                ]),
            )],
            ['style' => ['name' => $t('Svaki tjedan', 'Every week')], 'layout' => 'one', 'blocks' => [
                ['accordion', [
                    'heading' => $t('Svaki tjedan', 'Every week'),
                    'items' => [
                        ['question' => $t('Ponedjeljak: zatvoreno', 'Monday: closed'), 'answer' => $p('Kafić i dvorane su zatvoreni. Ateljei rade za članove s ključem.', 'The café and the halls are closed. Studios are open to members with a key.')],
                        ['question' => $t('Utorak: otvoreni atelje', 'Tuesday: open studio'), 'answer' => $p('Od 16 do 20 sati preše su slobodne za sve koji su prošli uvodnu radionicu.', 'From 4 to 8 the presses are free for anyone who has done the introduction.')],
                        ['question' => $t('Srijeda: crtanje za sve', 'Wednesday: drawing for all'), 'answer' => $p('Crtanje modela u 18 sati, papir i olovke su naši.', 'Life drawing at 6, paper and pencils provided.')],
                        ['question' => $t('Četvrtak: večernje radionice', 'Thursday: evening workshops'), 'answer' => $p('Drvorez, linorez i knjigoveštvo, prijava unaprijed.', 'Woodcut, linocut and bookbinding, booked ahead.')],
                        ['question' => $t('Petak: kino', 'Friday: cinema'), 'answer' => $p('Film u kino dvorani u 20 sati, 48 mjesta.', 'A film in the screening room at 8, 48 seats.')],
                        ['question' => $t('Subota: vodstvo i sajam', 'Saturday: tour and market'), 'answer' => $p('Vodstvo kroz izložbu u 11, sajam u dvorištu prve subote u mjesecu.', 'An exhibition tour at 11, a yard market on the first Saturday of the month.')],
                        ['question' => $t('Nedjelja: obitelji', 'Sunday: families'), 'answer' => $p('Tiskanje krumpirom i pečatima za djecu od četiri godine, od 10 do 13.', 'Potato and stamp printing for children from four, 10 to 1.')],
                    ],
                ], 'list', [], 0],
            ]],
            ['style' => ['name' => $t('Novosti', 'Newsletter'), 'surface' => 'contrast'], 'layout' => 'halves', 'blocks' => [
                ['text', [
                    'heading' => $t('Program na e-mail', 'The programme by email'),
                    'body' => $p('Jednom u dva tjedna: što dolazi, što se brzo popuni i koja je izložba sljedeća. Bez ičega drugog.', 'Once a fortnight: what is coming, what fills quickly and which exhibition is next. Nothing else.'),
                ], 'single', [], 0],
                ['form', [
                    'form' => 'demo:form:newsletter',
                ], 'stacked', [], 1],
            ]],
        ],
    ];
};
