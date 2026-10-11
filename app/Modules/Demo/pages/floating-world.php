<?php

/*
 * The Printworks: a past exhibition, Floating World (PLAN.md D-213), under Exhibitions: a
 * longer text, a picture, three more works and the leaflet to download.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    return [
        'key' => 'floating-world',
        'parent' => 'exhibitions',
        'slug' => $t('plutajuci-svijet', 'floating-world'),
        'title' => $t('Plutajući svijet', 'Floating World'),
        'description' => $t('Japanski drvorezi, 1780.–1850.: prošla izložba u The Printworks.', 'Japanese woodblock prints, 1780–1850: a past exhibition at The Printworks.'),
        'menu' => '',
        'sections' => [
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['text', [
                    'heading' => $t('Plutajući svijet', 'Floating World'),
                    'body' => $p('Japanski drvorezi, 1780.–1850. Prošla izložba.', 'Japanese woodblock prints, 1780–1850. A past exhibition.')
                        . '<h2>' . $t('Slike za svakoga', 'Pictures for everyone') . '</h2>'
                        . $p('U Edu je drvorez stajao koliko zdjela rezanaca. Izdavači su naručivali crteže, rezači su ih prenosili u trešnjino drvo, a tiskari otiskivali stotine listova, boju po boju. Kupovali su ih trgovci, glumci, putnici i djeca.', 'In Edo a woodblock print cost about as much as a bowl of noodles. Publishers commissioned drawings, cutters carried them into cherry wood, and printers pulled hundreds of sheets, one colour at a time. Merchants bought them, and actors, travellers and children.')
                        . '<h3>' . $t('Tri ruke na jednom listu', 'Three hands on one sheet') . '</h3>'
                        . $p('Na izložbi su uz grafike bili i alati: noževi, baren za trljanje papira i jedna izrezana ploča, posuđena od grafičarke koja i danas radi na isti način.', 'Beside the prints we showed the tools: knives, a baren for rubbing the paper and one cut block, lent by a printmaker who still works the same way.'),
                ], 'single', [], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['picture', [
                    'image' => 'demo-picture:art/great-wave-woodblock',
                    'caption' => $t('Veliki val, najpoznatiji list izložbe.', 'The Great Wave, the best-known sheet in the exhibition.'),
                ], 'inset', [], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['gallery', [
                    'heading' => $t('Još s izložbe', 'More from the exhibition'),
                    'items' => [
                        ['image' => 'demo-picture:art/surimono-still-life-stand', 'caption' => $t('Surimono s mrtvom prirodom', 'A surimono still life')],
                        ['image' => 'demo-picture:art/surimono-tobacco-pouch', 'caption' => $t('Surimono s torbicom za duhan', 'A surimono with a tobacco pouch')],
                        ['image' => 'demo-picture:art/night-rain-woodblock', 'caption' => $t('Noćna kiša', 'Night rain')],
                    ],
                ], 'three', ['shape' => 'natural'], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['downloads', [
                    'heading' => $t('Za ponijeti', 'To take away'),
                    'items' => [
                        ['file' => 'demo-file:floating-world-leaflet', 'title' => $t('Letak izložbe', 'Exhibition leaflet'), 'description' => $t('Jedna stranica, PDF.', 'One page, PDF.')],
                    ],
                ], 'list', [], 0],
            ]],
        ],
    ];
};
