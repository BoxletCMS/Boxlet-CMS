<?php

/*
 * The Printworks: hiring a room (PLAN.md D-213). The four rooms with their sizes, the terms
 * beside the documents (the narrow column first: the one section with 1fr + 2fr), and the
 * enquiry form.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    $room = static fn (string $hr, string $en, string $sizeHr, string $sizeEn, string $aboutHr, string $aboutEn, string $picture): array => [
        'heading' => $t($hr, $en),
        'body' => '<p><strong>' . $t($sizeHr, $sizeEn) . '</strong></p>' . $p($aboutHr, $aboutEn),
        'image' => 'demo-picture:' . $picture,
    ];

    return [
        'key' => 'hire',
        'slug' => $t('najam', 'hire'),
        'title' => $t('Najam prostora', 'Hire the space'),
        'description' => $t('Velika dvorana, studio, seminarska i kino dvorana za događaje, snimanja i sastanke.', 'The main hall, a studio, a seminar room and a screening room for events, shoots and meetings.'),
        'menu' => 'footer',
        'sections' => [
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['hero', [
                    'heading' => $t('Prostor za vaš događaj', 'Room for your event'),
                    'subheading' => $t('Od sastanka za dvanaest ljudi do sajma za dvjesto, u zgradi s dnevnim svjetlom i dobrom kavom.', 'From a meeting of twelve to a market for two hundred, in a building with daylight and good coffee.'),
                    'image' => 'demo-picture:space/empty-hall-tall-windows',
                    'cta' => ['label' => $t('Pošaljite upit', 'Send an enquiry'), 'url' => '#' . $t('upit', 'enquiry')],
                ], 'split', ['height' => 'tall'], 0],
            ]],
            ['style' => ['name' => $t('Prostori', 'The rooms')], 'layout' => 'one', 'blocks' => [
                ['cards', [
                    'heading' => $t('Četiri prostora', 'Four rooms'),
                    'items' => [
                        $room('Velika dvorana', 'Main hall', '420 m² · do 200 ljudi', '420 m² · up to 200 people', 'Nekadašnja tiskarska dvorana, sa sjevernim svjetlom i kranskom stazom.', 'The old press hall, with north light and the crane rail overhead.', 'space/empty-hall-tall-windows'),
                        $room('Studio', 'Studio', '90 m² · do 30 ljudi', '90 m² · up to 30 people', 'Periv pod i sudoper: za radionice i snimanja.', 'A washable floor and a sink: for workshops and shoots.', 'space/meeting-room-grey-chairs'),
                        $room('Seminarska dvorana', 'Seminar room', '60 m² · do 24 čovjeka', '60 m² · up to 24 people', 'Dvije ploče, platno i stolovi koji se slažu kako želite.', 'Two whiteboards, a screen and tables that go any way you like.', 'space/meeting-room-whiteboards'),
                        $room('Kino dvorana', 'Screening room', '48 sjedala', '48 seats', 'Projektor 4K i zvuk 5.1, za projekcije i predavanja.', 'A 4K projector and 5.1 sound, for screenings and talks.', 'space/cinema-curtain-red-seats'),
                    ],
                ], 'list', ['image_shape' => 'wide'], 0],
            ]],
            ['style' => ['name' => $t('Uvjeti', 'Terms')], 'layout' => 'wide-right', 'blocks' => [
                ['downloads', [
                    'heading' => $t('Dokumenti', 'Documents'),
                    'items' => [
                        ['file' => 'demo-file:technical-rider', 'title' => $t('Tehnički uvjeti', 'Technical rider'), 'description' => 'PDF'],
                        ['file' => 'demo-file:hire-prices', 'title' => $t('Cijene najma', 'Hire prices'), 'description' => 'PDF'],
                    ],
                ], 'list', [], 0],
                ['text', [
                    'heading' => $t('Uvjeti najma', 'Terms of hire'),
                    'body' => $p('Najam je po danu (9–23 h) ili po pola dana (četiri sata). Rezervacija se potvrđuje predujmom od 30 %, koji vraćamo u cijelosti ako otkažete 30 dana ranije.', 'Rooms are hired by the day (9 am–11 pm) or the half day (four hours). A booking is confirmed with a 30% deposit, returned in full if you cancel 30 days ahead.')
                        . $p('Udruge, škole i članovi plaćaju pola. Naš tehničar je uz vas na svakom najmu, a kafić može pripremiti kavu i ručak za vaše goste.', 'Charities, schools and members pay half. Our technician is with you for every booking, and the café can make coffee and lunch for your guests.'),
                ], 'single', [], 1],
            ]],
            ['style' => ['name' => $t('Upit', 'Enquiry'), 'anchor' => $t('upit', 'enquiry')], 'layout' => 'one', 'blocks' => [
                ['form', [
                    'heading' => $t('Pošaljite upit', 'Send an enquiry'),
                    'intro' => $t('Recite nam što planirate i kada; odgovaramo u dva radna dana.', 'Tell us what you are planning and when; we answer within two working days.'),
                    'form' => 'demo:form:hire',
                ], 'beside', [], 0],
            ]],
        ],
    ];
};
