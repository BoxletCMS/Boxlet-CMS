<?php

/*
 * The Printworks: the journal (PLAN.md D-213). Six short pieces as cards; Boxlet has no
 * articles, so each is a card, and the first has a page of its own (press-hall.php). Their
 * dates are counted back from the install, so the newest is always recent.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    $piece = static fn (int $daysAgo, string $hr, string $en, string $aboutHr, string $aboutEn, string $picture, string $page = ''): array => [
        'heading' => $t($hr, $en),
        'body' => '<p><strong>' . $on(-$daysAgo) . '</strong></p>' . $p($aboutHr, $aboutEn),
        'image' => 'demo-picture:' . $picture,
    ] + ($page === '' ? [] : ['link' => ['label' => $t('Pročitajte', 'Read on'), 'url' => 'demo:' . $page]]);

    return [
        'key' => 'journal',
        'slug' => $t('zapisi', 'journal'),
        'title' => $t('Zapisi', 'Journal'),
        'description' => $t('Bilješke iz The Printworks: obnova, radionice, izložbe i kafić.', 'Notes from The Printworks: the restoration, workshops, exhibitions and the café.'),
        'menu' => 'footer',
        'sections' => [
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['cards', [
                    'heading' => $t('Zapisi', 'Journal'),
                    'intro' => $t('Što se događa iza vrata: obnova, ljudi, papir i kava.', 'What happens behind the doors: the restoration, the people, the paper and the coffee.'),
                    'items' => [
                        $piece(4, 'Obnova tiskarske dvorane', 'Restoring the press hall', 'Kako smo spasili krov, prozore i jednu prešu iz 1912.', 'How we saved the roof, the windows and a press from 1912.', 'space/silo-spiral-stairs', 'press-hall'),
                        $piece(11, 'Prva ploča', 'The first block', 'Što se dogodi kad netko prvi put uzme dlijeto u ruke.', 'What happens when someone picks up a gouge for the first time.', 'workshop/linocut-ink-roller'),
                        $piece(19, 'Fjord u crno-bijelom', 'A fjord in black and white', 'Kustosica o jednom crtežu tušem i stotinu godina apstrakcije.', 'The curator on one ink drawing and a hundred years of abstraction.', 'art/fjord-heemskerck'),
                        $piece(26, 'Sedam šalica', 'Seven mugs', 'Zašto naš kafić ne koristi dvije iste šalice.', 'Why our café never uses two mugs alike.', 'food/mugs-wooden-table'),
                        $piece(33, 'Svjetlo odozgo', 'Light from above', 'Staklo, čelik i sto godina prašine na krovu dvorišta.', 'Glass, steel and a century of dust on the yard roof.', 'space/glass-gallery-roof'),
                        $piece(40, 'Knjige pod lišćem', 'Books under the leaves', 'Jesenski sajam rabljenih knjiga u dvorištu.', 'The autumn second-hand book fair in the yard.', 'books/open-book-autumn-leaves'),
                    ],
                ], 'grid', ['per_row' => '3', 'image_shape' => 'wide'], 0],
            ]],
        ],
    ];
};
