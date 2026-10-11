<?php

/*
 * The Printworks: visit and contact (PLAN.md D-213). Where and when, how to get here, and a
 * way to write. No map: the address is made up and a map always shows a real place (the
 * owner, D-213). Translated into the other language by the seed, like the home page.
 *
 * @param Closure(string, string): string $t
 * @param Closure(string, string): string $p
 * @param Closure(int, string): string $d
 * @param Closure(int): string $on
 */
return static function (Closure $t, Closure $p, Closure $d, Closure $on): array {
    return [
        'key' => 'visit',
        'slug' => $t('posjet', 'visit'),
        'title' => $t('Posjet', 'Visit'),
        'description' => $t('Adresa, radno vrijeme, pristup i kontakt The Printworks.', 'The address, opening hours, access and contact for The Printworks.'),
        'menu' => 'main',
        'sections' => [
            ['style' => ['name' => $t('Gdje i kada', 'Where and when')], 'layout' => 'halves', 'blocks' => [
                ['text', [
                    'heading' => $t('Posjetite nas', 'Visit us'),
                    'body' => $p('<strong>The Printworks</strong><br>Foundry Lane 14', '<strong>The Printworks</strong><br>14 Foundry Lane')
                        . '<h3>' . $t('Radno vrijeme', 'Opening hours') . '</h3>'
                        . '<ul><li>' . $t('Utorak–subota: 10–20', 'Tuesday–Saturday: 10 am–8 pm') . '</li><li>' . $t('Nedjelja: 10–15', 'Sunday: 10 am–3 pm') . '</li><li>' . $t('Ponedjeljak: zatvoreno', 'Monday: closed') . '</li></ul>'
                        . '<h3>' . $t('Pristup', 'Access') . '</h3>'
                        . $p('Ulaz iz dvorišta je bez stepenica, dizalo vodi do ateljea, a pristupačni toalet je u prizemlju uz kafić.', 'The yard entrance is step-free, a lift goes up to the studios, and the accessible toilet is on the ground floor by the café.'),
                ], 'single', [], 0],
                ['picture', [
                    'image' => 'demo-picture:space/brick-facade-green-door',
                    'caption' => $t('Ulaz iz dvorišta: zelena vrata.', 'The yard entrance: the green door.'),
                ], 'inset', ['shape' => 'square'], 1],
            ]],
            ['style' => ['name' => $t('Kako doći', 'Getting here')], 'layout' => 'one', 'blocks' => [
                ['accordion', [
                    'heading' => $t('Kako doći', 'Getting here'),
                    'items' => [
                        ['question' => $t('Javnim prijevozom', 'By public transport'), 'answer' => $p('Tramvaj do stanice Foundry Lane, pa dvije minute pješice. Noćni autobus staje ispred dvorišta.', 'The tram to Foundry Lane, then two minutes on foot. The night bus stops by the yard.')],
                        ['question' => $t('Parkiranje', 'Parking'), 'answer' => $p('Nemamo vlastito parkiralište. Javna garaža je pet minuta dalje; bicikle možete ostaviti u dvorištu.', 'We have no car park of our own. A public garage is five minutes away; bicycles can stay in the yard.')],
                        ['question' => $t('Pristupačnost', 'Accessibility'), 'answer' => $p('Sve dvorane i ateljei dostupni su bez stepenica. Za posjet uz pratnju ili s psom pomagačem nije potrebna najava.', 'Every hall and studio is step-free. You do not need to tell us ahead about a companion or an assistance dog.')],
                        ['question' => $t('Grupe i škole', 'Groups and schools'), 'answer' => $p('Vodstvo za grupe do 25 ljudi dogovaramo unaprijed; za škole je besplatno.', 'Tours for groups of up to 25 are booked ahead; they are free for schools.')],
                    ],
                ], 'list', [], 0],
            ]],
            ['style' => ['name' => $t('Kontakt', 'Contact')], 'layout' => 'one', 'blocks' => [
                ['form', [
                    'heading' => $t('Pišite nam', 'Write to us'),
                    'intro' => $t('Na pitanja odgovaramo u dva radna dana.', 'We answer questions within two working days.'),
                    'form' => 'demo:form:contact',
                ], 'beside', [], 0],
            ]],
        ],
    ];
};
