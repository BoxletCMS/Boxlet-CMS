<?php

/**
 * The Appearance screen (PLAN.md D-059): what the merged screen itself says.
 *
 * The decisions keep their own words in design.php and the header and footer keep theirs in
 * chrome.php — the screens merged, the vocabulary did not have to. Only what is new here is
 * new: the five tabs, the publishing, and the one line about which language the preview
 * draws.
 */

return [
    'appearance.title' => 'Izgled',
    'appearance.subhead' => 'Kako izgleda cijela web-stranica, uključujući zaglavlje i podnožje',
    'appearance.library.overwrite' => 'Spremi ono što je na zaslonu u „:name“',
    'appearance.back' => 'Napusti Izgled',
    'appearance.stage.viewport' => ':width px',
    // Said rather than silently acted on: the screen used to change the width by itself when
    // the column ran out of room, and a picture that changes under your hand reads as a
    // design that changed.
    'appearance.stage.tight' => 'Premalo prostora — odaberite užu širinu',
    // The last word of a card's summary: whether the page is a sheet or runs to the edges.
    'appearance.boxed' => 'uokvirena',
    'appearance.full_bleed' => 'do rubova',
    // SHORT, because six of them share 312px and a strip that wraps to two rows reads as
    // two strips. Each panel says the longer thing inside itself.
    // The strip over the picture: which page it is of (D-111).
    'appearance.page_to_preview' => 'Stranica za pretpregled',
    'appearance.publish' => 'Objavi',
    'appearance.published' => 'Objavljeno. Web-stranica od sada izgleda ovako.',

    // The toolbar over the picture.
    'appearance.width' => 'Širina',
    'appearance.width.desktop' => 'Računalo',
    'appearance.width.tablet' => 'Tablet',
    'appearance.width.phone' => 'Mobitel',
    'appearance.zoom' => 'Uvećanje',
    'appearance.zoom.fit' => 'Prilagodi',
    'appearance.compare' => 'Usporedi',
    'appearance.compare_hint' => 'Držite pritisnuto da vidite objavljenu web-stranicu.',
    'appearance.state.published' => 'Objavljeno',
    'appearance.state.unpublished' => 'Još nije objavljeno',
    'appearance.search' => 'Pretraži postavke',
    'appearance.search_placeholder' => 'Traži postavke — logotip, fiksno zaglavlje, kutovi…',
    'appearance.search_none' => 'Nijedna postavka ne odgovara pretraživanju.',
    // What each control is found by besides its own words (D-181), by key.
    'appearance.keywords.seed' => 'naglasak brend primarna glavna boja accent brand',
    'appearance.keywords.mode' => 'tamni svijetli noćni način tema dark',
    'appearance.keywords.secondary' => 'druga boja naglasak dvije',
    'appearance.keywords.surface_contrast' => 'trake obojena pozadina podloga kontrast',
    'appearance.keywords.color_background' => 'pozadina stranice',
    'appearance.keywords.color_card' => 'kartica okvir panel',
    'appearance.keywords.color_text' => 'tinta osnovni tekst boja teksta font',
    'appearance.keywords.color_link' => 'poveznice linkovi podcrtano',
    'appearance.keywords.heading_font' => 'font pismo fontovi serifni bezserifni naslovni mono naslov',
    'appearance.keywords.body_font' => 'font pismo fontovi serifni bezserifni mono tekst osnovni odlomak',
    'appearance.keywords.text_size' => 'veličina fonta osnovni tekst veći manji',
    'appearance.keywords.scale' => 'naslovi veličina omjer ljestvica',
    'appearance.keywords.line_height' => 'prored razmak redaka',
    'appearance.keywords.heading_weight' => 'podebljano debljina debeli naslovi',
    'appearance.keywords.tracking' => 'razmak slova',
    'appearance.keywords.caps' => 'velika slova verzal',
    'appearance.keywords.spacing' => 'unutarnji razmak praznine bjelina prostor',
    'appearance.keywords.section_gap' => 'trake okomiti ritam razmak između sekcija',
    'appearance.keywords.radius' => 'kutovi zaobljeno oblo zaobljenost',
    'appearance.keywords.border_width' => 'crte rubovi obrub',
    'appearance.keywords.shadow' => 'sjene dubina izdignutost',
    'appearance.keywords.container' => 'širina sadržaja mjera najveća širina',
    'appearance.keywords.boxed' => 'list okvir uokvirena stranica',
    'appearance.keywords.page_background' => 'pozadina izvana okolina',
    'appearance.keywords.header_arrangement' => 'logotip logo izbornik raspored navigacija',
    'appearance.keywords.brand' => 'logotip logo naziv web-stranice znak',
    'appearance.keywords.logo_size' => 'logotip logo',
    'appearance.keywords.header_behaviour' => 'fiksno ljepljivo prozirno preko naslovne sekcije pomicanje sticky',
    'appearance.keywords.header_opacity' => 'prozirno providno',
    'appearance.keywords.header_blur' => 'zamućeno mliječno staklo',
    'appearance.keywords.nav_style' => 'izbornik poveznice navigacija kapsule',
    'appearance.keywords.header_button' => 'poziv na akciju gumb cta',
    'appearance.keywords.footer_layout' => 'stupci podnožje',
    'appearance.keywords.small_print_row' => 'autorska prava copyright pravne napomene',
    'appearance.keywords.button_style' => 'gumbi obrub ispunjeni prozirni',
    'appearance.keywords.button_radius' => 'gumbi kutovi kapsula zaobljeni',
    'appearance.keywords.button_height' => 'gumbi veličina visoki',
    'appearance.keywords.button_caps' => 'gumbi velika slova verzal',
    'appearance.regions_hint' => 'Kliknite bilo koji dio stranice da otvorite njegove postavke',
    'appearance.history' => 'Poništi i ponovi',
    'appearance.undo' => 'Poništi (⌘Z)',
    'appearance.redo' => 'Ponovi (⇧⌘Z)',
    'appearance.state.changes_one' => ':count neobjavljena promjena',
    'appearance.state.changes_few' => ':count neobjavljene promjene',
    'appearance.state.changes_many' => ':count neobjavljenih promjena',
    'appearance.state.problem' => 'Ispravite kontrast da biste mogli objaviti',
    'appearance.revert' => 'Odbaci promjene',

    // Designs the owner keeps (D-061).
    'appearance.library' => 'Vaši dizajni',
    'appearance.library.empty' => 'Još ništa nije sačuvano. Uredite zaslon kako želite i sačuvajte ga pod nekim imenom.',
    'appearance.library.name' => 'Naziv ovog dizajna',
    'appearance.library.save' => 'Sačuvaj ovaj dizajn',
    'appearance.library.use' => 'Koristi ovaj dizajn',
    'appearance.library.delete' => 'Obriši',
    'appearance.library.delete_one' => 'Obriši „:name“',
    'appearance.library.saved' => 'Sačuvano kao „:name“. Web-stranica se nije promijenila — za to pritisnite Objavi.',
    'appearance.library.overwritten' => 'Dizajn „:name“ sada sadrži ono što je na ovom zaslonu. Web-stranica se nije promijenila.',
    'appearance.library.loaded' => 'Dizajn „:name“ je na zaslonu. Pritisnite Objavi da ga postavite na web-stranicu.',
    'appearance.library.deleted' => 'Dizajn „:name“ je obrisan. Ono što je na zaslonu ostaje netaknuto.',
    'appearance.library.name_needed' => 'Najprije dajte dizajnu ime.',
    'appearance.library.from' => 'Prema karakteru :character',
    'appearance.library.by_hand' => 'Izrađen ručno',

    // A design as a file, out and in (PLAN.md D-152).
    'appearance.export' => 'Izvezi ovaj dizajn',
    'appearance.export_one' => 'Izvezi „:name“ kao datoteku',
    'appearance.export_name' => 'Moj dizajn',
    'appearance.export_missing' => 'Tog dizajna više nema.',
    'appearance.import' => 'Uvezi dizajn…',
    'appearance.import_label' => 'Datoteka dizajna za Boxlet (.json), izvezena s ove ili neke druge web-stranice.',
    'appearance.import_send' => 'Uvezi',
    'appearance.import_no_file' => 'Najprije odaberite datoteku dizajna.',
    'appearance.import_refused' => 'Ovaj dizajn nije moguće uvesti: :reason',
    'appearance.import_question' => 'Uvesti „:name“?',
    'appearance.import_warnings' => 'Izostavljeno pri uvozu:',
    'appearance.import_design_only' => 'Ova datoteka sadrži dizajn bez kompozicije: može se učitati na zaslon, ali ne i dodati kao karakter.',
    'appearance.import_add' => 'Dodaj kao karakter',
    'appearance.import_add_hint' => 'Čuva se među karakterima pa ga možete učitati kad god želite. Na web-stranici se ništa ne mijenja.',
    'appearance.import_load' => 'Učitaj na zaslon',
    'appearance.import_load_hint' => 'Popunjava zaslon tim dizajnom, kao kad učitate karakter. Na web-stranici se ništa ne mijenja dok ne objavite.',
    'appearance.import_cancel' => 'Odustani',
    'appearance.import_added' => '„:name“ dodan je kao karakter.',
    'appearance.import_loaded' => 'Dizajn „:name“ učitan je na zaslon. Na web-stranici se ništa ne mijenja dok ne objavite.',
    'appearance.import_gone' => 'Više nema dizajna koji čeka uvoz. Ponovno uvezite datoteku.',
    'appearance.custom' => 'Vlastiti',
    'appearance.character_delete' => 'Obriši karakter „:name“',
    'appearance.character_delete_confirm' => 'Obrisati karakter „:name“? Stranice zadržavaju svoje sekcije. Ako je web-stranica složena prema njemu, od sada se slaže prema karakteru Minimalistički.',
    'appearance.character_deleted' => 'Karakter „:name“ je obrisan.',
    'appearance.skipped' => 'Ove datoteke dizajna izostavljene su. Web-stranica radi i bez njih:',
    'appearance.character_missing' => 'Karakter „:name“ prema kojem je ova web-stranica složena više ne postoji. Novi blokovi slažu se prema karakteru :default dok ne odaberete drugi.',
];
