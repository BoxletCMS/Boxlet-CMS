<?php

/*
 * The Printworks: membership (PLAN.md D-213). Three levels as a price list, what the money
 * does, the questions, and the way in.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    $level = static fn (string $nameHr, string $nameEn, string $price, array $hr, array $en): array => [
        'heading' => $t($nameHr, $nameEn) . ' · ' . $price,
        // One list or the other: $t chooses between words, this between lists.
        'body' => '<ul><li>' . implode('</li><li>', $t('hr', 'en') === 'hr' ? $hr : $en) . '</li></ul>',
        'link' => ['label' => $t('Učlanite se', 'Join'), 'url' => 'demo:visit'],
    ];

    return [
        'key' => 'membership',
        'slug' => $t('clanstvo', 'membership'),
        'title' => $t('Članstvo', 'Membership'),
        'description' => $t('Postanite član The Printworks: od 30 € godišnje.', 'Become a member of The Printworks: from €30 a year.'),
        'menu' => 'main',
        'sections' => [
            // Close to the header and to the levels under it, and tall rather than Couture's
            // whole screen (the owner, D-216).
            ['style' => ['align' => 'center', 'pad_top' => '20', 'pad_bottom' => '0'], 'layout' => 'one', 'blocks' => [
                ['hero', [
                    'heading' => $t('Kuća koju drže njezini članovi', 'A house kept by its members'),
                    'subheading' => $t('Devetsto ljudi plaća dio krova, struje i radionica. Zauzvrat dobivaju manje cijene, ranije termine i ključ od kuće.', 'Nine hundred people pay for part of the roof, the power and the workshops. In return they get lower prices, early booking and a key to the house.'),
                ], 'center', ['height' => 'tall'], 0],
            ]],
            ['style' => ['name' => $t('Razine', 'Levels')], 'layout' => 'one', 'blocks' => [
                ['cards', [
                    'heading' => $t('Tri razine', 'Three levels'),
                    'items' => [
                        $level('Prijatelj', 'Friend', $t('30 € godišnje', '€30 a year'),
                            ['Program na e-mail prije svih', '10 % popusta u kafiću i knjižari', 'Večeri samo za članove', 'Glas na godišnjoj skupštini'],
                            ['The programme before anyone else', '10% off in the café and bookshop', 'Members\' evenings', 'A vote at the annual meeting']),
                        $level('Član', 'Member', $t('60 € godišnje', '€60 a year'),
                            ['Sve što ima Prijatelj', 'Radionice po članskoj cijeni', 'Upis na radionice tjedan dana ranije', 'Utorkom besplatno korištenje preša', 'Jedan gost na svakom događaju'],
                            ['Everything a Friend has', 'Workshops at the members\' price', 'Booking a week before everyone else', 'The presses free on Tuesdays', 'A guest at every event']),
                        $level('Pokrovitelj', 'Patron', $t('150 € godišnje', '€150 a year'),
                            ['Sve što ima Član', 'Ključ od ateljea za vikende', 'Dva besplatna mjesta na radionicama', 'Vaše ime na zidu u dvorištu', 'Vodstvo kroz izložbu s kustosicom'],
                            ['Everything a Member has', 'A studio key at weekends', 'Two free workshop places', 'Your name on the wall in the yard', 'A tour of each exhibition with the curator']),
                    ],
                ], 'grid', ['per_row' => '3', 'image_shape' => 'none'], 0],
            ]],
            ['style' => ['name' => $t('Što članarina plaća', 'What membership pays for'), 'surface' => 'contrast'], 'layout' => 'one', 'blocks' => [
                ['stats', [
                    'heading' => $t('Što članarina plaća', 'What membership pays for'),
                    'items' => [
                        ['value' => '96', 'label' => $t('besplatnih mjesta za mlade', 'free places for young people')],
                        ['value' => '1/3', 'label' => $t('troškova grijanja', 'of the heating bill')],
                        ['value' => '11', 'label' => $t('izložbi godišnje', 'exhibitions a year')],
                    ],
                ], 'three', [], 0],
            ]],
            ['style' => ['name' => $t('Pitanja', 'Questions')], 'layout' => 'one', 'blocks' => [
                ['accordion', [
                    'heading' => $t('O članstvu', 'About membership'),
                    'items' => [
                        ['question' => $t('Kada počinje članstvo?', 'When does membership start?'), 'answer' => $p('Onog dana kad platite, i traje dvanaest mjeseci.', 'The day you pay, and it lasts twelve months.')],
                        ['question' => $t('Mogu li članstvo pokloniti?', 'Can I give membership as a present?'), 'answer' => $p('Da. Na pultu kafića dobit ćete karticu u omotnici, otisnutu na našoj preši.', 'Yes. At the café counter you get a card in an envelope, printed on our press.')],
                        ['question' => $t('Postoji li popust za studente?', 'Is there a student price?'), 'answer' => $p('Studenti i umirovljenici plaćaju pola za svaku razinu.', 'Students and pensioners pay half at every level.')],
                    ],
                ], 'list', [], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['cta', [
                    'heading' => $t('Učlanite se na pultu ili u dvorištu', 'Join at the counter or in the yard'),
                    'body' => $t('Svaki dan osim ponedjeljka, od 10 do 20 sati.', 'Every day except Monday, from 10 to 8.'),
                    'action' => ['label' => $t('Kako do nas', 'How to find us'), 'url' => 'demo:visit'],
                ], 'banner', [], 0],
            ]],
        ],
    ];
};
