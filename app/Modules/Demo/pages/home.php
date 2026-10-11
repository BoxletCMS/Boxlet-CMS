<?php

/*
 * The Printworks: home (PLAN.md D-213). The whole centre in one page: what it is, its three
 * halls of activity, this week, the building's story, the rooms, a word from a visitor, the
 * partners and the invitation to join.
 *
 * Every block but two follows the character (D-194, the owner): its layout and options are
 * left empty, so the home page shows what each design set composes. The four numbers keep a
 * row of four (a character's row of three left the fourth alone on a line), and this week's
 * events a list with no picture area, which no character's cards would give them.
 *
 * Translated into the other language by the seed (DemoSite::translate), so its sections
 * keep one order in both: the translation takes each block's words by position.
 *
 * @param Closure(string, string): string $t the Croatian words or the English
 * @param Closure(string, string): string $p the same, as a paragraph
 * @param Closure(int, string): string $d an event's day and time, this many days after the install
 * @param Closure(int): string $on that day's date alone
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    $event = static fn (int $days, string $time, string $hr, string $en, string $whereHr, string $whereEn): array => [
        'heading' => $t($hr, $en),
        'body' => '<p><strong>' . $d($days, $time) . '</strong> · ' . $t($whereHr, $whereEn) . '</p>',
        'link' => ['label' => $t('Detalji', 'Details'), 'url' => 'demo:program'],
    ];

    return [
        'key' => 'home',
        'slug' => '',
        'title' => $t('Početna', 'Home'),
        'description' => $t('Tiskara iz 1898., od 2019. kulturni centar: izložbe, radionice, kafić i knjižara.', 'A printing works from 1898, a cultural centre since 2019: exhibitions, workshops, a café and a bookshop.'),
        'menu' => '',
        'sections' => [
            ['style' => ['name' => $t('Uvod', 'Intro')], 'layout' => 'one', 'blocks' => [
                ['hero', [
                    'heading' => $t('Tiskara, ponovno otvorena za sve.', 'A printing works, reopened for everyone.'),
                    'subheading' => $t('Izložbe, radionice, koncerti i dobra kava u zgradi u kojoj su se sto godina slagala slova.', 'Exhibitions, workshops, concerts and good coffee in a building where type was set for a hundred years.'),
                    'cta' => ['label' => $t('Što je na programu', 'What\'s on'), 'url' => 'demo:program'],
                    'image' => 'demo-picture:space/abandoned-workshop-hall',
                ], '', [], 0],
            ]],
            ['style' => ['name' => $t('U brojkama', 'In numbers'), 'surface' => 'contrast', 'animation' => 'fade'], 'layout' => 'one', 'blocks' => [
                ['stats', [
                    'items' => [
                        ['value' => '1898', 'label' => $t('otvorena tiskara', 'the press opened')],
                        ['value' => '120+', 'label' => $t('događaja godišnje', 'events a year')],
                        // The unit under the number: "2,400 m²" broke in two at a display size (Couture, Event).
                        ['value' => $t('2.400', '2,400'), 'label' => $t('m² dvorana i ateljea', 'm² of halls and studios')],
                        ['value' => '900', 'label' => $t('članova', 'members')],
                    ],
                ], 'four', [], 0],
            ]],
            ['style' => ['name' => $t('Program', 'Programme'), 'anchor' => 'program'], 'layout' => 'one', 'blocks' => [
                ['cards', [
                    'heading' => $t('Tri stvari koje radimo', 'Three things we do'),
                    'items' => [
                        ['heading' => $t('Izložbe', 'Exhibitions'), 'body' => $p('Grafika, papir i slovo, od starih majstora do onih koji tek počinju.', 'Prints, paper and type, from old masters to those just starting out.'), 'image' => 'demo-picture:art/great-wave-woodblock', 'link' => ['label' => $t('Izložbe', 'Exhibitions'), 'url' => 'demo:exhibitions']],
                        ['heading' => $t('Radionice', 'Workshops'), 'body' => $p('Linorez, sitotisak, keramika i šivanje, u malim grupama.', 'Linocut, screen printing, ceramics and sewing, in small groups.'), 'image' => 'demo-picture:workshop/potter-hands-wheel', 'link' => ['label' => $t('Radionice', 'Workshops'), 'url' => 'demo:workshops']],
                        ['heading' => $t('Kafić i knjižara', 'Café & bookshop'), 'body' => $p('Kava iz male pržionice, juha dana i police knjiga o tisku.', 'Coffee from a small roaster, the day\'s soup and shelves of books about print.'), 'image' => 'demo-picture:food/mugs-wooden-table', 'link' => ['label' => $t('Kafić i knjižara', 'Café & bookshop'), 'url' => 'demo:cafe']],
                    ],
                ], '', [], 0],
            ]],
            ['style' => ['name' => $t('Ovaj tjedan', 'This week')], 'layout' => 'wide-left', 'blocks' => [
                ['text', [
                    'heading' => $t('Ovaj tjedan', 'This week'),
                    'body' => $p('Velika dvorana ovaj tjedan pripada papiru: izložba Površina i uzorak otvorena je svaki dan od 10 do 20 sati, a ulaz je slobodan.', 'This week the main hall belongs to paper: Surface & Pattern is open every day from 10 to 8, and entry is free.')
                        . $p('Na radionice se prijavljuje unaprijed, a mjesta se brzo popune. Ako je radionica puna, upišite se na listu čekanja; javimo se čim se mjesto oslobodi.', 'Workshops are booked ahead and fill quickly. If one is full, join the waiting list; we write as soon as a place comes free.')
                        . $p('U utorak poslijepodne preše su otvorene za sve koji su prošli uvodnu radionicu: donesite svoju ploču ili papir i otisnite što ste započeli kod kuće. Tom je u tiskari od četiri do osam i pomaže s bojom i pritiskom.', 'On Tuesday afternoon the presses are open to anyone who has done the introduction: bring your block or your paper and print what you started at home. Tom is in the press hall from four to eight to help with ink and pressure.')
                        . $p('Kafić radi kao i uvijek, od osam ujutro, a u subotu u dvorištu ima kruha iz pekare na kraju ulice. Ako dolazite na koncert, dođite ranije: kuhinja zatvara u osam.', 'The café keeps its usual hours from eight in the morning, and on Saturday there is bread from the bakery at the end of the street in the yard. If you are coming to the concert, come early: the kitchen closes at eight.'),
                ], '', [], 0],
                ['cards', [
                    'items' => [
                        $event(1, '19:00', 'Razgovor: Kako se reže ploča', 'Talk: Cutting the block', 'Kino dvorana', 'Screening room'),
                        $event(3, '18:00', 'Radionica linoreza za početnike', 'Linocut for beginners', 'Studio', 'Studio'),
                        $event(5, '20:30', 'Koncert u tiskarskoj dvorani', 'A concert in the press hall', 'Velika dvorana', 'Main hall'),
                    ],
                ], 'list', ['image_shape' => 'none'], 1],
            ]],
            ['style' => ['name' => $t('Zgrada', 'The building')], 'layout' => 'one', 'blocks' => [
                ['image_text', [
                    'heading' => $t('Iz tiskarske dvorane u javnu dvoranu', 'From press hall to public hall'),
                    'body' => $p('Tiskara je radila od 1898. do 1987. Kad su se strojevi utišali, zgrada je trideset godina čekala. Susjedi, tiskari u mirovini i nekoliko umjetnika otvorili su je ponovno 2019.', 'The press ran from 1898 until 1987. When the machines stopped, the building waited for thirty years. Neighbours, retired printers and a few artists opened it again in 2019.')
                        . $p('Krov je novi, a prozori su stari. Slova su i dalje u kasama.', 'The roof is new and the windows are old. The type is still in its cases.'),
                    'link' => ['label' => $t('Povijest zgrade', 'The building\'s story'), 'url' => 'demo:about'],
                    'image' => 'demo-picture:archive/steam-press-progress-century',
                ], '', [], 0],
            ]],
            ['style' => ['name' => $t('Prostori', 'The rooms')], 'layout' => 'one', 'blocks' => [
                ['gallery', [
                    'heading' => $t('Pogledajte okolo', 'Have a look around'),
                    'items' => [
                        ['image' => 'demo-picture:space/cafe-mezzanine-brick', 'caption' => $t('Kafić i galerija', 'The café and the mezzanine')],
                        ['image' => 'demo-picture:space/empty-hall-tall-windows', 'caption' => $t('Velika dvorana', 'The main hall')],
                        ['image' => 'demo-picture:space/letterpress-proof-press', 'caption' => $t('Preša za otiske', 'The proofing press')],
                        ['image' => 'demo-picture:space/glass-gallery-roof', 'caption' => $t('Staklena galerija', 'The glass gallery')],
                        ['image' => 'demo-picture:space/concrete-stairs-handrail', 'caption' => $t('Stube do ateljea', 'The stairs to the studios')],
                        ['image' => 'demo-picture:space/brick-facade-green-door', 'caption' => $t('Ulaz iz dvorišta', 'The yard entrance')],
                    ],
                ], '', [], 0],
            ]],
            ['style' => ['name' => $t('Riječ posjetitelja', 'A visitor\'s word'), 'surface' => 'image', 'image' => 'demo-picture:texture/kraft-cardboard'], 'layout' => 'one', 'blocks' => [
                ['quote', [
                    'quote' => $t('„Došla sam na kavu, ostala na radionici, a sada imam vlastiti ključ ateljea.”', '“I came for a coffee, stayed for a workshop, and now I have my own studio key.”'),
                    'attribution' => 'Nina',
                    'role' => $t('članica od 2021.', 'member since 2021'),
                ], '', [], 0],
            ]],
            ['style' => ['name' => $t('Partneri', 'Partners')], 'layout' => 'one', 'blocks' => [
                ['logos', [
                    'heading' => $t('Uz potporu', 'With the support of'),
                    'items' => [
                        ['name' => 'Northgate Arts Fund'],
                        ['name' => 'Harbour Paper Mill'],
                        ['name' => 'The Ink Society'],
                        ['name' => 'Kestrel Books'],
                        ['name' => 'Lanes & Yards Trust'],
                    ],
                ], '', [], 0],
            ]],
            ['style' => ['name' => $t('Članstvo', 'Membership')], 'layout' => 'one', 'blocks' => [
                ['cta', [
                    'heading' => $t('Postanite član', 'Become a member'),
                    'body' => $t('Od 30 € godišnje: popusti na radionice, rani upis i večeri samo za članove.', 'From €30 a year: lower workshop prices, early booking and members\' evenings.'),
                    'action' => ['label' => $t('Postanite član', 'Become a member'), 'url' => 'demo:membership'],
                    'second' => ['label' => $t('Posjet', 'Plan a visit'), 'url' => 'demo:visit'],
                ], '', [], 0],
            ]],
        ],
    ];
};
