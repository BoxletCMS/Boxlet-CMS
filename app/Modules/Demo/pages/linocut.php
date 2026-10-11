<?php

/*
 * The Printworks: one workshop, Linocut (PLAN.md D-213), under Workshops: what it is, when,
 * what to bring, and the way to sign up.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    return [
        'key' => 'linocut',
        'parent' => 'workshops',
        'slug' => $t('linorez', 'linocut'),
        'title' => $t('Linorez', 'Linocut'),
        'description' => $t('Linorez za početnike i one koji se vraćaju: termini, cijene i popis materijala.', 'Linocut for beginners and those who come back: dates, prices and what to bring.'),
        'menu' => '',
        'sections' => [
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['image_text', [
                    'heading' => $t('Linorez', 'Linocut'),
                    'body' => $p('Linoleum je mekan, a dlijeto oštro: za tri sata izrežete ploču i otisnete deset listova na našoj preši. Ne treba znati crtati, treba imati strpljenja za jednu crtu.', 'Lino is soft and the gouge is sharp: in three hours you cut a block and pull ten prints on our press. You do not need to draw; you need patience for one line.')
                        . $p('Vodi Tom, koji linorez predaje dvadeset godina.', 'Led by Tom, who has taught linocut for twenty years.'),
                    'image' => 'demo-picture:workshop/linocut-ink-roller',
                    'image_fit' => 'contain',
                ], 'image-left', [], 0],
            ]],
            ['style' => [], 'layout' => 'wide-left', 'blocks' => [
                ['text', [
                    'heading' => $t('Termini', 'Dates'),
                    'body' => '<ul>'
                        . '<li><strong>' . $d(3, '18:00') . '</strong> · ' . $t('uvodna radionica, 3 sata', 'introduction, 3 hours') . '</li>'
                        . '<li><strong>' . $d(10, '18:00') . '</strong> · ' . $t('uvodna radionica, 3 sata', 'introduction, 3 hours') . '</li>'
                        . '<li><strong>' . $d(12, '18:30') . '</strong> · ' . $t('večernji termin: boja u više slojeva', 'evening session: printing in layers') . '</li>'
                        . '</ul>'
                        . $p('Uvodna radionica 45 €, članovi 35 €. Večernji termin 25 €, članovi 18 €.', 'Introduction €45, members €35. Evening session €25, members €18.'),
                ], 'single', [], 0],
                ['downloads', [
                    'heading' => $t('Prije dolaska', 'Before you come'),
                    'items' => [
                        ['file' => 'demo-file:linocut-materials', 'title' => $t('Što ponijeti', 'What to bring'), 'description' => $t('Popis materijala, PDF.', 'Materials list, PDF.')],
                    ],
                ], 'cards', [], 1],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['cta', [
                    'heading' => $t('Prijavite se na linorez', 'Sign up for linocut'),
                    'body' => $t('Osam mjesta po terminu.', 'Eight places a session.'),
                    'action' => ['label' => $t('Prijava', 'Sign up'), 'url' => 'demo:workshops'],
                ], 'beside', [], 0],
            ]],
        ],
    ];
};
