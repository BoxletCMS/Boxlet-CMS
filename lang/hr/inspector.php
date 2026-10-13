<?php

/**
 * The Appearance screen's inspector (PLAN.md D-157, D-158, D-160): its home, its sections and
 * groups, the owner's changes over the character, and the contrast check in a line. The
 * decisions keep their words in design.php and the header and footer in chrome.php.
 */

return [
    'inspector.home' => 'Sve postavke',
    'inspector.quick' => 'Brzi početak',
    'inspector.character' => 'Karakter',
    'inspector.detailed' => 'Detaljno',
    'inspector.back' => 'Sve postavke',
    'inspector.export' => 'Izvezi',
    'inspector.delete' => 'Obriši',
    'inspector.use' => 'Primijeni',
    'inspector.tile_menu' => 'Više mogućnosti za „:name“',

    'inspector.section.colours' => 'Boje',
    'inspector.section.typography' => 'Tipografija',
    'inspector.section.space' => 'Razmak i oblik',
    'inspector.section.layout' => 'Raspored i širine',
    'inspector.section.header' => 'Zaglavlje',
    'inspector.section.footer' => 'Podnožje',

    'inspector.group.colours.basics' => 'Osnove',
    'inspector.group.colours.palette' => 'Paleta',
    'inspector.group.colours.contrast' => 'Kontrast',
    'inspector.group.typography.typeface' => 'Font',
    'inspector.group.typography.sizes' => 'Veličine',
    'inspector.group.typography.headings' => 'Naslovi',
    'inspector.group.typography.fine' => 'Fino podešavanje',
    'inspector.group.space.spacing' => 'Razmak',
    'inspector.group.space.shape' => 'Oblik',
    'inspector.group.space.shadow' => 'Sjena',
    'inspector.group.layout.diagram' => 'Pregled',
    'inspector.group.layout.content' => 'Sadržaj',
    'inspector.group.layout.page' => 'Stranica',
    'inspector.group.layout.chrome' => 'Zaglavlje i podnožje',
    'inspector.group.header.arrangement' => 'Raspored',
    'inspector.group.header.behaviour' => 'Ponašanje',
    'inspector.group.header.background' => 'Pozadina',
    'inspector.group.header.menu' => 'Izbornik i gumb',
    'inspector.group.footer.arrangement' => 'Raspored',
    'inspector.group.footer.background' => 'Pozadina',

    // The line under each section's name, built from the values on the screen.
    'inspector.summary.colours' => ':seed · čitljivo :passing/:total',
    'inspector.summary.typography' => ':fonts · :size px · ljestvica :scale',
    'inspector.summary.space' => 'razmak :space px · zaobljenost :radius px · sjena :shadow',
    'inspector.summary.layout_boxed' => 'list :sheet px · sadržaj :content px',
    'inspector.summary.layout_full' => 'puna širina · sadržaj :content px',

    'inspector.layout.sheet' => 'List :sheet px',
    'inspector.layout.full_width' => 'Puna širina',
    'inspector.layout.content' => 'Sadržaj :content px',
    'inspector.layout.chrome' => 'Zaglavlje i podnožje',

    // How much is the owner's own, over the character the screen shows (D-158).
    'inspector.overrides_one' => ':count vlastita promjena u odnosu na karakter :character',
    'inspector.overrides_many' => ':count vlastitih promjena u odnosu na karakter :character',
    'inspector.overrides_few' => ':count vlastite promjene u odnosu na karakter :character',
    'inspector.reset.one' => 'Vrati „:name“ na vrijednost karaktera',
    'inspector.reset.section' => 'Vrati odjeljak na karakter',
    'inspector.reset.all' => 'Vrati sve',
    'inspector.reset.done' => 'Vraćeno na vrijednost karaktera. Web-stranica se nije promijenila — za to pritisnite „Objavi“.',
    'inspector.reset.all_done' => 'Sve je vraćeno na vrijednosti karaktera. Web-stranica se nije promijenila — za to pritisnite „Objavi“.',

    // Publish's question after a character is loaded (D-068), with a way out of it (D-161).
    'inspector.apply.restyles_one' => ':count sekcija kojoj ste ručno uredili izgled ponovno dobiva izgled karaktera.',
    'inspector.apply.restyles_few' => ':count sekcije kojima ste ručno uredili izgled ponovno dobivaju izgled karaktera.',
    'inspector.apply.restyles_many' => ':count sekcija kojima ste ručno uredili izgled ponovno dobiva izgled karaktera.',
    'inspector.apply.own_layouts_one' => ':count blok s vlastitim rasporedom zadržava svoj raspored.',
    'inspector.apply.own_layouts_few' => ':count bloka s vlastitim rasporedom zadržavaju svoj raspored.',
    'inspector.apply.own_layouts_many' => ':count blokova s vlastitim rasporedom zadržava svoj raspored.',
    'inspector.apply.cancel' => 'Odustani',
    'inspector.apply.cancel_hint' => 'Natrag na dizajn kakav je objavljen. Na web-stranici se ništa ne mijenja.',

    'inspector.palette_note' => 'Izračunato iz glavne boje. Odaberite boju da biste postavili vlastitu — točkica pokazuje da je vaša.',
    'inspector.palette_all' => 'Prikaži još :count uloga',
    'inspector.palette_all_one' => 'Prikaži još :count ulogu',
    'inspector.palette_all_few' => 'Prikaži još :count uloge',

    // The contrast check in one line (D-160).
    'inspector.contrast.ok' => 'Svi su parovi čitljivi',
    'inspector.contrast.fail_one' => ':count par ima prenizak kontrast — objava nije moguća',
    'inspector.contrast.fail_few' => ':count para imaju prenizak kontrast — objava nije moguća',
    'inspector.contrast.fail_many' => ':count parova ima prenizak kontrast — objava nije moguća',
    'inspector.contrast.fix' => 'Popravi automatski',
    'inspector.contrast.change_seed' => 'Neki parovi ne prolaze zbog same glavne ili druge boje. Promijenite tu boju da biste ih popravili.',
    'inspector.contrast.every' => 'Svi parovi (:count)',

    // Global decisions v2 (D-164).
    'inspector.section.buttons' => 'Gumbi',
    'inspector.group.buttons.style' => 'Stil',
    'inspector.summary.buttons' => ':style · :corners',
];
