<?php

/*
 * The Printworks: about (PLAN.md D-213). The building's history, four values side by side,
 * the trade it came from, the people who run it (first names and roles, no photographs),
 * the partners and the annual report.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    $value = static fn (int $column, string $hr, string $en, string $aboutHr, string $aboutEn): array => ['text', [
        'heading' => $t($hr, $en),
        'body' => $p($aboutHr, $aboutEn),
    ], 'single', [], $column];
    $person = static fn (string $name, string $hr, string $en): array => [
        'heading' => $name,
        'body' => $p($hr, $en),
    ];

    return [
        'key' => 'about',
        'slug' => $t('o-nama', 'about'),
        'title' => $t('O nama', 'About'),
        'description' => $t('Tiskara od 1898. do 1987., kulturni centar od 2019.: povijest, vrijednosti i ljudi The Printworks.', 'A printing works from 1898 to 1987, a cultural centre since 2019: the history, values and people of The Printworks.'),
        'menu' => 'footer',
        'sections' => [
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['image_text', [
                    'heading' => $t('Sto godina tiska', 'A hundred years of print'),
                    'body' => $p('Tiskara na adresi Foundry Lane 14 otvorena je 1898. s dvije preše i šestero slagara. Na vrhuncu, sedamdesetih, tiskala je novine za cijelu regiju, plakate za kazalište i vozne redove za željeznicu.', 'The printing works at 14 Foundry Lane opened in 1898 with two presses and six compositors. At its height, in the seventies, it printed the region\'s newspaper, the theatre\'s posters and the railway\'s timetables.')
                        . $p('Zatvorena je 1987. Zgrada je trideset godina stajala prazna, dok je udruga susjeda nije otkupila i, uz pomoć tiskara u mirovini, ponovno otvorila 2019.', 'It closed in 1987. The building stood empty for thirty years, until a neighbours\' association bought it and, with the help of retired printers, opened it again in 2019.'),
                    'image' => 'demo-picture:archive/printing-workshop-engraving',
                    'image_fit' => 'contain',
                ], 'image-left', [], 0],
            ]],
            ['style' => ['name' => $t('Vrijednosti', 'Values')], 'layout' => 'quarters', 'blocks' => [
                $value(0, 'Otvoreno', 'Open', 'Izložbe su besplatne, dvorište je otvoreno svima, a kava je ista za sve.', 'Exhibitions are free, the yard is open to everyone, and the coffee is the same for all.'),
                $value(1, 'Rukom', 'Hands-on', 'Ovdje se uči radeći: svaka izložba ima radionicu, svaka radionica otisak.', 'Here you learn by doing: every exhibition has a workshop, every workshop a print.'),
                $value(2, 'Lokalno', 'Local', 'Kava, kruh, papir i ljudi dolaze iz kvarta, kad god je to moguće.', 'Coffee, bread, paper and people come from the neighbourhood whenever they can.'),
                $value(3, 'Pristupačno', 'Affordable', 'Pola cijene za mlade, studente i umirovljenike, i besplatna mjesta svaki mjesec.', 'Half price for the young, students and pensioners, and free places every month.'),
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['image_text', [
                    'heading' => $t('Zanat iz kojeg smo došli', 'The trade we came from'),
                    'body' => $p('Tiskare su nekad imale vlastite posjetnice: male oglase koji su pokazivali što sve slova mogu. Skupljamo ih, a nekoliko ih je na zidu kafića.', 'Printers once had cards of their own: small advertisements showing everything their type could do. We collect them, and a few hang on the café wall.'),
                    'image' => 'demo-picture:archive/printers-trade-card',
                    'image_fit' => 'contain',
                ], 'image-right', [], 0],
            ]],
            ['style' => ['name' => $t('Tim', 'Team')], 'layout' => 'one', 'blocks' => [
                ['cards', [
                    'heading' => $t('Tko vodi kuću', 'Who runs the house'),
                    'items' => [
                        $person('Ana', 'ravnateljica', 'director'),
                        $person('Lea', 'izložbe', 'exhibitions'),
                        $person('Tom', 'radionice', 'workshops'),
                        $person('Marko', 'tehnika i zgrada', 'technician and building'),
                        $person('Iva', 'kafić i knjižara', 'café and bookshop'),
                        $person('Sam', 'članovi i volonteri', 'members and volunteers'),
                    ],
                ], 'grid', ['per_row' => '3', 'image_shape' => 'none'], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['logos', [
                    'heading' => $t('Partneri', 'Partners'),
                    'items' => [
                        ['name' => 'Northgate Arts Fund'],
                        ['name' => 'Harbour Paper Mill'],
                        ['name' => 'The Ink Society'],
                        ['name' => 'Kestrel Books'],
                        ['name' => 'Lanes & Yards Trust'],
                    ],
                ], 'grid', [], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['downloads', [
                    'items' => [
                        ['file' => 'demo-file:annual-report', 'title' => $t('Godišnje izvješće', 'Annual report'), 'description' => $t('Godina ukratko, PDF.', 'The year in short, PDF.')],
                    ],
                ], 'list', [], 0],
            ]],
        ],
    ];
};
