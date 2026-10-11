<?php

/*
 * THE SHOWROOM (PLAN.md D-167): one page that shows every block in every layout it has, and
 * every value of every section style the other pages do not. Published and not in the menu.
 *
 * Not in the demo's menu, and kept on purpose: the demo is the visual regression fixture
 * (tests/demo_test.php), and The Printworks' pages use the blocks an arts centre needs, not
 * every layout of each (D-213). A page that is a catalogue teaches nobody what a page looks
 * like, so it is a page of its own rather than spread over the others.
 *
 * @param Closure(string, string): string $t the Croatian words or the English
 * @param Closure(string, string): string $p the same, as a paragraph
 * @return array{key: string, slug: string, title: string, description: string, menu: string, sections: list<array{style: array<string, string>, layout: string, blocks: list<array{string, array<string, mixed>, string, array<string, string>, int}>}>}
 */
return static function (Closure $t, Closure $p): array {
    $one = static fn (array $style, array $block): array => ['style' => $style, 'layout' => 'one', 'blocks' => [$block]];
    $label = static fn (string $hr, string $en): array => ['text', ['heading' => $t($hr, $en), 'body' => $p('Primjer bloka.', 'An example of the block.')], 'single', [], 0];

    return [
        'key' => 'blocks',
        'slug' => $t('blokovi', 'blocks'),
        'title' => $t('Svi blokovi', 'Every block'),
        'description' => $t('Svaki blok u svakom rasporedu.', 'Every block in every layout.'),
        'menu' => '',
        'sections' => [
            $one(['align' => 'center', 'animation' => 'up'], ['hero', [
                'heading' => $t('Sve što stane na stranicu', 'Everything a page can hold'),
                'subheading' => $t('Svaki blok, u svakom svom obliku. Promijenite karakter i svi se mijenjaju s njim.', 'Every block, in each of its shapes. Change the character and all of them change with it.'),
                'cta' => ['label' => $t('Natrag na početnu', 'Back home'), 'url' => 'demo:home'],
                // Centred here since the home page's hero follows its character, split (D-176):
                // between them the demo still shows every hero layout.
            ], 'center', ['height' => 'tall'], 0]),
            $one(['surface' => 'gradient', 'min_height' => '50', 'v_align' => 'center'], ['hero', [
                'heading' => $t('Riječi u sredini', 'Words in the middle'),
                'subheading' => $t('Slika iza riječi, najsvjetlija sjena.', 'A picture behind the words, the lightest shade.'),
            ], 'cover-center', ['veil' => 'light'], 0]),
            $one(['surface' => 'image', 'divider' => 'slant'], ['hero', [
                'heading' => $t('Riječi lijevo', 'Words on the left'),
                'subheading' => $t('Visok, sjena se povlači od riječi.', 'Tall, the shade clearing away from the words.'),
            ], 'cover-left', ['height' => 'tall', 'veil' => 'medium'], 0]),
            $one(['width' => 'wide'], ['hero', [
                'heading' => $t('Riječi desno', 'Words on the right'),
                'subheading' => $t('Ista sjena, okrenuta na drugu stranu.', 'The same shade, turned the other way.'),
            ], 'cover-right', ['height' => 'tall', 'veil' => 'medium'], 0]),
            $one(['width' => 'full', 'animation' => 'zoom'], ['hero', [
                'heading' => $t('Riječi nisko', 'Words low on the left'),
                'subheading' => $t('Najjača sjena, skupljena pod riječima.', 'The strongest shade, gathered under the words.'),
            ], 'cover-low', ['height' => 'screen', 'veil' => 'strong'], 0]),
            // Left, said out loud: a character that centres everything would otherwise leave
            // the demo without a left-aligned section to look at.
            $one(['divider' => 'line', 'align' => 'left'], ['text', [
                'heading' => $t('Tekst u dva stupca', 'Text in two columns'),
                // Long enough to fill two columns under every character: a paragraph of two
                // lines is never split, so a short one stood in one column (D-191).
                'body' => $p('Duži tekst teče u dva stupca na širokom zaslonu i u jedan na mobitelu. Naslov ostaje iznad oba. Svaki stupac drži svoju duljinu retka, pa se tekst čita jednako ugodno kao u jednom stupcu, samo kraće. Kad zaslon postane preuzak za dva stupca od barem šesnaest rema, tekst se sam vraća u jedan.', 'A longer text runs in two columns on a wide screen and one on a phone. The heading stays above both. Each column keeps its own line length, so the text reads as comfortably as in one column, only shorter. When the screen grows too narrow for two columns of at least sixteen rem, the text returns to one by itself.'),
            ], 'columns', [], 0]),
            $one(['surface' => 'contrast', 'divider' => 'curve'], ['cta', [
                'heading' => $t('Poziv u jednom redu', 'A call in one row'),
                'body' => $t('Tekst lijevo, gumb desno.', 'Words on the left, the button on the right.'),
                'action' => ['label' => $t('Javite se', 'Get in touch'), 'url' => 'demo:visit'],
                'second' => ['label' => $t('Program', 'What\'s on'), 'url' => 'demo:program'],
            ], 'beside', [], 0]),
            $one(['min_height' => '30', 'v_align' => 'top'], $label('Razmak', 'Space')),
            $one([], ['divider', ['height' => 'medium'], 'space', [], 0]),
            $one(['min_height' => '30', 'v_align' => 'bottom'], $label('Crta', 'A line')),
            $one([], ['divider', ['height' => 'small'], 'line', [], 0]),
            $one([], ['downloads', ['heading' => $t('Cjenici i obrasci', 'Price lists and forms'), 'items' => [
                ['title' => $t('Cjenik', 'Price list'), 'description' => $t('Sve usluge i njihove cijene.', 'Every service and what it costs.')],
                ['title' => $t('Upitnik', 'Questionnaire'), 'description' => $t('Ispunite ga prije sastanka.', 'Fill it in before we meet.')],
            ]], 'list', [], 0]),
            $one([], ['downloads', ['heading' => $t('Brošure', 'Brochures'), 'items' => [
                ['title' => $t('Studio', 'The studio'), 'description' => $t('Tko smo, na četiri stranice.', 'Who we are, in four pages.')],
                ['title' => $t('Proces', 'Our process'), 'description' => $t('Od prvog razgovora do ključa.', 'From the first talk to the keys.')],
            ]], 'cards', [], 0]),
            $one([], ['embed', ['url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'caption' => $t('Video preko cijele širine', 'A video, full width'), 'ratio' => 'wide'], 'full', [], 0]),
            $one([], ['embed', ['url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'caption' => $t('Video, uvučen', 'A video, inset'), 'ratio' => 'wide'], 'inset', [], 0]),
            $one([], ['form', ['heading' => $t('Obrazac jedan ispod drugog', 'A form, stacked'), 'form' => 'demo:form:contact'], 'stacked', [], 0]),
            $one([], ['gallery', ['heading' => $t('Dvije, kakve jesu', 'Two, as they are'), 'items' => [['caption' => $t('Radionica', 'The workshop')], ['caption' => $t('Uzorci', 'Samples')]]], 'two', ['shape' => 'natural'], 0]),
            $one([], ['gallery', ['heading' => $t('Tri, kvadratne', 'Three, square'), 'items' => [['caption' => 'A'], ['caption' => 'B'], ['caption' => 'C']]], 'three', ['shape' => 'square'], 0]),
            $one([], ['gallery', ['heading' => $t('Četiri, okrugle', 'Four, round'), 'items' => [['caption' => 'Ana'], ['caption' => 'Marko'], ['caption' => 'Petra'], ['caption' => 'Ivan']]], 'four', ['shape' => 'round'], 0]),
            $one([], ['logos', ['heading' => $t('Klijenti u redu', 'Clients in a row'), 'items' => [['name' => 'Marić'], ['name' => 'Sjever'], ['name' => 'Ilica'], ['name' => 'Kovač']]], 'row', [], 0]),
            $one([], ['logos', ['heading' => $t('Klijenti u mreži', 'Clients in a grid'), 'items' => [['name' => 'Marić'], ['name' => 'Sjever'], ['name' => 'Ilica'], ['name' => 'Kovač'], ['name' => 'Babić'], ['name' => 'Lumen']]], 'grid', [], 0]),
            $one([], ['picture', ['caption' => $t('Slika preko stupca', 'A picture filling the column')], 'full', ['shape' => 'wide'], 0]),
            $one([], ['picture', ['caption' => $t('Slika s prostorom oko sebe', 'A picture with room around it')], 'inset', ['shape' => 'wide'], 0]),
            // The picture on the left, which the home page showed by hand until its blocks followed
            // the character (D-194): the demo's, Soft, puts it on the right.
            $one([], ['image_text', ['heading' => $t('Slika lijevo', 'The picture on the left'), 'body' => $p('Riječi uz sliku, s njezine desne strane.', 'Words beside the picture, on its right.')], 'image-left', [], 0]),
            $one([], ['quote', ['quote' => $t('„Citat na kartici.”', '“A quotation on a card.”'), 'attribution' => 'Ana M.'], 'card', [], 0]),
            $one([], ['stats', ['heading' => $t('Četiri broja', 'Four numbers'), 'items' => [
                ['value' => '15', 'label' => $t('godina', 'years')],
                ['value' => '200+', 'label' => $t('prostora', 'spaces')],
                ['value' => '3', 'label' => $t('arhitekta', 'architects')],
                ['value' => '1', 'label' => $t('radionica', 'workshop')],
            ]], 'four', [], 0]),
            $one(['width' => 'narrow'], ['accordion', ['heading' => $t('Pitanja kao kartice', 'Questions as cards'), 'items' => [
                ['question' => $t('Što je blok?', 'What is a block?'), 'answer' => $p('Jedna stvar na stranici: naslov, slika, red brojeva.', 'One thing on a page: a heading, a picture, a row of numbers.')],
                ['question' => $t('Što je sekcija?', 'What is a section?'), 'answer' => $p('Traka u kojoj blok stoji. Ima pozadinu, razmak i širinu.', 'The band a block stands in. It has the background, the spacing and the width.')],
            ], 'start' => 'closed'], 'cards', [], 0]),
        ],
    ];
};
