<?php

/*
 * The Printworks: one journal article, Restoring the press hall (PLAN.md D-213), under
 * Journal: a long text with headings and a list, a picture, a quote and a video. The video
 * is a museum's own, on YouTube, about the work the article describes.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    return [
        'key' => 'press-hall',
        'parent' => 'journal',
        'slug' => $t('obnova-tiskarske-dvorane', 'restoring-the-press-hall'),
        'title' => $t('Obnova tiskarske dvorane', 'Restoring the press hall'),
        'description' => $t('Kako smo spasili krov, prozore i jednu prešu iz 1912.', 'How we saved the roof, the windows and a press from 1912.'),
        'menu' => '',
        'sections' => [
            ['style' => ['width' => 'narrow'], 'layout' => 'one', 'blocks' => [
                ['text', [
                    'heading' => $t('Obnova tiskarske dvorane', 'Restoring the press hall'),
                    'body' => '<p><strong>' . $on(-4) . '</strong></p>'
                        . $p('Kad smo prvi put otvorili vrata, u dvorani je padala kiša. Krov je propuštao na četrdeset mjesta, a ispod najveće rupe stajala je preša iz 1912., prekrivena ceradom koju je netko prije trideset godina pažljivo zavezao.', 'The first time we opened the doors, it was raining inside the hall. The roof leaked in forty places, and under the largest hole stood a press from 1912, covered with a tarpaulin someone had tied down carefully thirty years before.')
                        . '<h2>' . $t('Krov prvi', 'The roof first') . '</h2>'
                        . $p('Novac za krov skupili su članovi i susjedi, tri godine zaredom. Krovne rešetke su ostale; zamijenili smo samo ono što se više nije dalo popraviti.', 'Members and neighbours raised the money for the roof, three years running. The trusses stayed; we replaced only what could no longer be mended.')
                        . '<h3>' . $t('Što smo naučili', 'What we learned') . '</h3>'
                        . '<ul><li>' . $t('Stari prozori se mogu popraviti, samo treba strpljiv stolar.', 'Old windows can be mended; it takes a patient joiner.') . '</li>'
                        . '<li>' . $t('Preša bez ulja godinama i dalje radi, kad joj se ulje vrati.', 'A press without oil for years still runs once the oil goes back in.') . '</li>'
                        . '<li>' . $t('Tiskari u mirovini pamte sve: gdje je stajala koja kasa i zašto.', 'Retired printers remember everything: where each case stood, and why.') . '</li></ul>'
                        . '<h2>' . $t('Preša ponovno radi', 'The press runs again') . '</h2>'
                        . $p('Prvi otisak nakon trideset godina bio je jedan redak: ime ulice. Danas na preši tiskamo pozivnice za izložbe i članske iskaznice.', 'The first print in thirty years was a single line: the name of the street. Today the press prints our exhibition invitations and membership cards.'),
                ], 'single', [], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['picture', [
                    'image' => 'demo-picture:space/letterpress-proof-press',
                    'caption' => $t('Preša za otiske, očišćena i podmazana.', 'The proofing press, cleaned and oiled.'),
                ], 'full', ['shape' => 'wide'], 0],
            ]],
            ['style' => ['width' => 'narrow'], 'layout' => 'one', 'blocks' => [
                ['quote', [
                    'quote' => $t('Nismo je obnovili da bi stajala u muzeju. Obnovili smo je da bi radila.', 'We did not restore it to stand in a museum. We restored it to work.'),
                    'attribution' => 'Marko',
                    'role' => $t('tehničar', 'technician'),
                ], 'big', [], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['embed', [
                    'url' => 'https://www.youtube.com/watch?v=0cz7linE5PE',
                    'caption' => $t('Kako se rastavlja slog nakon tiska: video Sacramento History Museuma.', 'Breaking down a form after printing: a video by the Sacramento History Museum.'),
                    'ratio' => 'wide',
                ], 'inset', [], 0],
            ]],
        ],
    ];
};
