<?php

/*
 * The Printworks: café & bookshop (PLAN.md D-213). The two rooms by the entrance, the
 * hours, the menu with its prices, what the staff are reading, and a look at both.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    $list = static fn (array $items): string => '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';

    return [
        'key' => 'cafe',
        'slug' => $t('kafic-i-knjizara', 'cafe-bookshop'),
        'title' => $t('Kafić i knjižara', 'Café & Bookshop'),
        'description' => $t('Kava, juha dana i knjige o tisku, papiru i slovima, uz ulaz u The Printworks.', 'Coffee, the day\'s soup and books about print, paper and type, by the entrance of The Printworks.'),
        'menu' => 'main',
        'sections' => [
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['image_text', [
                    'heading' => $t('Kafić', 'The café'),
                    'body' => $p('Kafić je u nekadašnjoj slagaonici, uz dugi šank od starog radnog stola. Kavu prži mala pržionica dvije ulice dalje, a kruh stiže svako jutro u sedam.', 'The café is in the old composing room, along a counter made from the compositors\' bench. The coffee comes from a small roaster two streets away, and the bread arrives every morning at seven.'),
                    'image' => 'demo-picture:space/bar-stools-counter',
                ], 'image-left', [], 0],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['image_text', [
                    'heading' => $t('Knjižara', 'The bookshop'),
                    'body' => $p('Knjige o tisku, papiru, slovima i ručnom radu, nove i rabljene, i police lokalnih izdavača. Rabljene knjige otkupljujemo utorkom.', 'Books about print, paper, type and making things, new and second-hand, and a shelf of local publishers. We buy second-hand books on Tuesdays.'),
                    'image' => 'demo-picture:books/bookshop-aisle',
                ], 'image-right', [], 0],
            ]],
            ['style' => ['name' => $t('Kafić i knjige', 'Café and books')], 'layout' => 'thirds', 'blocks' => [
                ['text', [
                    'heading' => $t('Radno vrijeme', 'Opening hours'),
                    'body' => $list([
                        $t('Utorak–petak: 8–20', 'Tuesday–Friday: 8 am–8 pm'),
                        $t('Subota: 9–20', 'Saturday: 9 am–8 pm'),
                        $t('Nedjelja: 9–15', 'Sunday: 9 am–3 pm'),
                        $t('Ponedjeljak: zatvoreno', 'Monday: closed'),
                    ]),
                ], 'single', [], 0],
                ['text', [
                    'heading' => $t('Kava i hrana', 'Coffee & food'),
                    'body' => $list([
                        $t('Espresso — 1,80 €', 'Espresso — €1.80'),
                        $t('Kava s mlijekom — 2,60 €', 'Flat white — €2.60'),
                        $t('Juha dana s kruhom — 5,50 €', 'Soup of the day with bread — €5.50'),
                        $t('Sendvič od kiselog tijesta — 6,90 €', 'Sourdough sandwich — €6.90'),
                        $t('Kolač iz pekare — 3,20 €', 'Cake from the bakery — €3.20'),
                    ]),
                ], 'single', [], 1],
                ['text', [
                    'heading' => $t('Preporuke osoblja', 'Staff picks'),
                    'body' => $p('<strong>Iva:</strong> priručnik o japanskom uvezu, s uzorcima koje možete prekopirati.', '<strong>Iva:</strong> a handbook of Japanese binding, with patterns you can copy.')
                        . $p('<strong>Tom:</strong> povijest drvenih slova iz tvornice koja ih je radila stotinu godina.', '<strong>Tom:</strong> a history of wood type from the factory that made it for a hundred years.'),
                ], 'single', [], 2],
            ]],
            ['style' => [], 'layout' => 'one', 'blocks' => [
                ['gallery', [
                    'items' => [
                        ['image' => 'demo-picture:food/pastry-counter', 'caption' => $t('Kolači na pultu', 'Cakes on the counter')],
                        ['image' => 'demo-picture:food/espresso-white-table', 'caption' => 'Espresso'],
                        ['image' => 'demo-picture:food/sandwich-flowers-tea', 'caption' => $t('Sendvič i čaj', 'A sandwich and tea')],
                        ['image' => 'demo-picture:food/coffee-smile', 'caption' => $t('Kava s osmijehom', 'Coffee with a smile')],
                        ['image' => 'demo-picture:books/leather-bound-shelves', 'caption' => $t('Stare knjige u kožnom uvezu', 'Old leather-bound books')],
                        ['image' => 'demo-picture:books/book-stall-rows', 'caption' => $t('Štand rabljenih knjiga', 'The second-hand stall')],
                    ],
                ], 'three', ['shape' => 'wide'], 0],
            ]],
        ],
    ];
};
