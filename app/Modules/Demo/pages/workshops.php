<?php

/*
 * The Printworks: workshops (PLAN.md D-213). Six crafts, the sign-up form beside how it
 * works (on a phone the form comes first: the section's order reversed), and the questions
 * people ask. Linocut has a page of its own (linocut.php).
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    $craft = static fn (string $hr, string $en, string $aboutHr, string $aboutEn, string $picture, string $page = ''): array => [
        'heading' => $t($hr, $en),
        'body' => $p($aboutHr, $aboutEn),
        'image' => 'demo-picture:' . $picture,
    ] + ($page === '' ? [] : ['link' => ['label' => $t('Više', 'More'), 'url' => 'demo:' . $page]]);

    return [
        'key' => 'workshops',
        'slug' => $t('radionice', 'workshops'),
        'title' => $t('Radionice', 'Workshops'),
        'description' => $t('Linorez, sitotisak, keramika, šivanje, papir i knjigoveštvo, tipografija: male grupe, sav alat je naš.', 'Linocut, screen printing, ceramics, sewing, paper and binding, letterpress: small groups, all tools provided.'),
        'menu' => 'main',
        'sections' => [
            ['style' => ['align' => 'center'], 'layout' => 'one', 'blocks' => [
                ['hero', [
                    'heading' => $t('Radionice', 'Workshops'),
                    'subheading' => $t('Najviše osam ljudi, jedan voditelj i sav alat koji treba. Dođite s idejom, otiđite s otiskom.', 'Eight people at most, one tutor and every tool you need. Come with an idea, leave with a print.'),
                    'image' => 'demo-picture:workshop/wooden-type-case',
                ], 'center', ['height' => 'auto'], 0],
            ]],
            ['style' => ['name' => $t('Zanati', 'The crafts')], 'layout' => 'one', 'blocks' => [
                ['cards', [
                    'heading' => $t('Šest zanata', 'Six crafts'),
                    'intro' => $t('Svaka radionica ima uvodni termin za početnike i večernje termine za one koji se vraćaju.', 'Every craft has an introduction for beginners and evening sessions for those who come back.'),
                    'items' => [
                        $craft('Linorez', 'Linocut', 'Izrežite ploču i otisnite je na preši.', 'Cut a block and print it on the press.', 'workshop/linocut-ink-roller', 'linocut'),
                        $craft('Sitotisak', 'Screen printing', 'Od skice do torbe ili majice u jednoj večeri.', 'From a sketch to a bag or a shirt in an evening.', 'workshop/screen-printing-inks'),
                        $craft('Keramika', 'Ceramics', 'Lončarsko kolo, glina i peć u dvorištu.', 'The wheel, the clay and the kiln in the yard.', 'workshop/clay-wheel'),
                        $craft('Šivanje', 'Sewing', 'Krojevi, strojevi i popravci odjeće.', 'Patterns, machines and mending.', 'workshop/sewing-machine-needle'),
                        $craft('Papir i uvez', 'Paper & binding', 'Ručno uvezane bilježnice i kutije.', 'Hand-bound notebooks and boxes.', 'workshop/cut-paper-stacks'),
                        $craft('Tipografija', 'Letterpress', 'Slaganje olovnih slova i otisak na staroj preši.', 'Setting lead type and printing on the old press.', 'space/metal-type-close'),
                    ],
                ], 'grid', ['per_row' => '3', 'image_shape' => 'square'], 0],
            ]],
            ['style' => ['name' => $t('Prijava', 'Sign up')], 'layout' => 'halves', 'stack' => 'reverse', 'blocks' => [
                ['text', [
                    'heading' => $t('Kako se prijaviti', 'How to sign up'),
                    'body' => $p('Odaberite radionicu i pošaljite prijavu. Javit ćemo se u dva dana s terminom i uputama za plaćanje.', 'Choose a workshop and send the form. We write within two days with a date and how to pay.')
                        . '<ul><li>' . $t('Uvodna radionica: 45 €, članovi 35 €', 'Introduction: €45, members €35') . '</li><li>' . $t('Večernji termin: 25 €, članovi 18 €', 'Evening session: €25, members €18') . '</li><li>' . $t('Materijal je uključen u cijenu', 'Materials are included') . '</li></ul>',
                ], 'single', [], 0],
                ['form', [
                    'heading' => $t('Prijava na radionicu', 'Workshop sign-up'),
                    'form' => 'demo:form:workshop',
                ], 'stacked', [], 1],
            ]],
            ['style' => ['name' => $t('Pitanja', 'Questions')], 'layout' => 'one', 'blocks' => [
                ['accordion', [
                    'heading' => $t('Česta pitanja', 'Questions people ask'),
                    'items' => [
                        ['question' => $t('Trebam li ponijeti materijal?', 'Do I need to bring materials?'), 'answer' => $p('Ne. Sve je uključeno; ponesite samo pregaču ili staru odjeću.', 'No. Everything is included; bring an apron or old clothes.')],
                        ['question' => $t('Od koje dobi?', 'From what age?'), 'answer' => $p('Od 14 godina. Mlađi su dobrodošli uz odraslu osobu, a nedjeljom imamo radionice za obitelji.', 'From 14. Younger people are welcome with an adult, and Sundays have family sessions.')],
                        ['question' => $t('Što ako ne mogu doći?', 'What if I cannot come?'), 'answer' => $p('Javite nam se najkasnije dva dana ranije i prebacit ćemo vas na drugi termin ili vratiti novac.', 'Let us know two days ahead and we move you to another date or refund you.')],
                        ['question' => $t('Je li prostor pristupačan?', 'Is the building accessible?'), 'answer' => $p('Studio i velika dvorana su u prizemlju, bez stepenica. Ateljei na katu imaju dizalo.', 'The studio and the main hall are on the ground floor, step-free. The upstairs studios have a lift.')],
                    ],
                    'start' => 'first-open',
                ], 'cards', [], 0],
            ]],
        ],
    ];
};
