<?php

/*
 * The Printworks: exhibitions (PLAN.md D-213). The current one, Surface & Pattern, under a
 * picture with a veil; its works; its curator; and three past exhibitions, the first of which
 * has a page of its own (floating-world.php).
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    return [
        'key' => 'exhibitions',
        'slug' => $t('izlozbe', 'exhibitions'),
        'title' => $t('Izložbe', 'Exhibitions'),
        'description' => $t('Površina i uzorak: tiskani papir od 1500. do 1900. Ulaz slobodan.', 'Surface & Pattern: printed paper from 1500 to 1900. Free entry.'),
        'menu' => 'main',
        'sections' => [
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['hero', [
                    'heading' => $t('Površina i uzorak', 'Surface & Pattern'),
                    'subheading' => $t('Tiskani papir od 1500. do 1900. U velikoj dvorani, do kraja sezone, svaki dan od 10 do 20 sati.', 'Printed paper from 1500 to 1900. In the main hall until the end of the season, every day from 10 to 8.'),
                    'image' => 'demo-picture:art/still-life-lemon-pewter',
                    'cta' => ['label' => $t('Planirajte posjet', 'Plan your visit'), 'url' => 'demo:visit'],
                ], 'cover-left', ['height' => 'tall', 'veil' => 'light'], 0],
            ]],
            ['style' => ['name' => $t('O izložbi', 'About the exhibition')], 'layout' => 'one', 'blocks' => [
                ['image_text', [
                    'heading' => $t('Slova od ljudi i leptira', 'Letters made of people and butterflies'),
                    'body' => $p('Prije nego što je papir postao bijel i prazan, tiskari su ga ukrašavali: abecedama sastavljenim od likova, uzorcima za korice knjiga, reklamama koje su same bile male slike.', 'Before paper became white and empty, printers dressed it up: alphabets built of figures, patterns for book covers, advertisements that were small pictures in themselves.')
                        . $p('Izložba okuplja pedesetak listova iz četiri stoljeća. Većinu možete pogledati izbliza, kroz povećalo koje dobijete na ulazu.', 'The exhibition gathers some fifty sheets from four centuries. Most can be seen up close, through the magnifier you are given at the door.'),
                    'image' => 'demo-picture:art/decorated-alphabet-wachsmuth',
                    'image_fit' => 'contain',
                ], 'image-right', [], 0],
            ]],
            ['style' => ['name' => $t('Djela', 'The works')], 'layout' => 'one', 'blocks' => [
                ['gallery', [
                    'heading' => $t('S izložbe', 'From the exhibition'),
                    'items' => [
                        ['image' => 'demo-picture:art/red-pattern-paper', 'caption' => $t('Papir s crvenim uzorkom, 18. st.', 'Paper with a red pattern, 18th c.')],
                        ['image' => 'demo-picture:art/blue-pattern-paper', 'caption' => $t('Papir s plavim uzorkom, 19. st.', 'Paper with a blue pattern, 19th c.')],
                        ['image' => 'demo-picture:art/speckled-pattern-paper', 'caption' => $t('Papir s točkicama, 19. st.', 'Speckled paper, 19th c.')],
                        ['image' => 'demo-picture:art/woodcut-ornament-pattern-book', 'caption' => $t('Knjiga uzoraka, 1563.', 'A pattern book, 1563')],
                        ['image' => 'demo-picture:art/roman-alphabet-hopfer', 'caption' => $t('Daniel Hopfer, rimska abeceda, oko 1520.', 'Daniel Hopfer, Roman alphabet, c. 1520')],
                        ['image' => 'demo-picture:art/lithographers-ornate-advertisement', 'caption' => $t('Reklama litografa, 19. st.', 'A lithographer\'s advertisement, 19th c.')],
                    ],
                ], 'three', ['shape' => 'square'], 0],
            ]],
            ['style' => ['name' => $t('Kustosica', 'The curator')], 'layout' => 'halves', 'blocks' => [
                ['text', [
                    'heading' => $t('Riječ kustosice', 'From the curator'),
                    'body' => $p('Ovi listovi nisu nastali da bi visjeli na zidu. Bili su omoti, predlošci, oglasi i vježbe. Upravo zato o tisku govore više od mnogih slika: vidi se kako je ruka žurila, gdje je boja bila prerijetka, koji je uzorak netko volio pa ga ponavljao.', 'These sheets were never made to hang on a wall. They were wrappers, patterns, advertisements and exercises. That is why they say more about printing than many paintings do: you can see where a hand hurried, where the ink ran thin, which pattern someone loved and repeated.'),
                ], 'single', [], 0],
                ['quote', [
                    'quote' => $t('Uzorak je strpljenje koje se vidi.', 'A pattern is patience you can see.'),
                    'attribution' => 'Lea',
                    'role' => $t('kustosica izložbe', 'curator of the exhibition'),
                ], 'card', [], 1],
            ]],
            ['style' => ['name' => $t('Prošle izložbe', 'Past exhibitions')], 'layout' => 'one', 'blocks' => [
                ['cards', [
                    'heading' => $t('Prošle izložbe', 'Past exhibitions'),
                    'items' => [
                        ['heading' => $t('Plutajući svijet', 'Floating World'), 'body' => $p('Japanski drvorezi, 1780.–1850.', 'Japanese woodblock prints, 1780–1850.'), 'image' => 'demo-picture:art/night-rain-woodblock', 'link' => ['label' => $t('O izložbi', 'About the exhibition'), 'url' => 'demo:floating-world']],
                        ['heading' => $t('Mrtve prirode', 'Still Lifes'), 'body' => $p('Knjige, voće i svijećnjaci iz tri stoljeća.', 'Books, fruit and candlesticks from three centuries.'), 'image' => 'demo-picture:art/still-life-books-candlestick'],
                        ['heading' => $t('Botanica', 'Botanica'), 'body' => $p('Biljke kako su ih crtali prije fotografije.', 'Plants as they were drawn before photography.'), 'image' => 'demo-picture:art/climbing-lily-botanical'],
                    ],
                ], 'grid', ['image_shape' => 'square'], 0],
            ]],
        ],
    ];
};
