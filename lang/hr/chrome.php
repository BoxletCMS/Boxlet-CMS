<?php

// The site's header and footer (PLAN.md D-028, D-030). A new concern, so a new file:
// t() merges every file in the locale's directory, and a key defined twice is a test
// failure rather than a silent winner.
//
// Written IN THE SAME BREATH as views/chrome.php. Calling t() for a key that does not
// exist yet put a bare key on a screen in 5a, and an untranslated aria-label on the front
// end earlier today; the order is what prevents it, not care.

return [
    'chrome.title' => 'Zaglavlje i podnožje',
    'chrome.intro' => 'Ono što stoji na vrhu i na dnu svake stranice. Postavlja se jednom za cijelu web-stranicu.',

    'chrome.shared' => 'Isto na svim jezicima',
    'chrome.words' => 'Tekst',
    // Each tab holds its own half of the words (D-111), folded per language when there are several.
    'chrome.words.header' => 'Tekst zaglavlja',
    'chrome.words.footer' => 'Tekst podnožja',


    'chrome.logo_where' => 'Logotip se postavlja zajedno s ostalim slikama web-stranice, u odjeljku',
    'chrome.logo_where_link' => 'Postavke → Vizualni identitet.',
    'chrome.menu' => 'Izbornik',
    'chrome.menu_hint' => 'Koji se izbornik prikazuje u zaglavlju. Izbornici se izrađuju na dnu ove stranice; izbornik istog naziva na svakom jeziku daje svakom prijevodu vlastiti tekst.',
    'chrome.menu_none' => 'Bez izbornika',
    'chrome.no_menus' => 'Još nema nijednog izbornika. Izradite ga na dnu ove stranice i bit će ponuđen ovdje.',
    // Where the words went (D-180): Appearance keeps how they look.
    'chrome.header_words_where' => 'Izbornik u zaglavlju te tekst i adresa gumba postavljaju se u odjeljku',
    'chrome.footer_words_where' => 'Naslovi, tekst i izbornici stupaca te sitna slova postavljaju se u odjeljku',
    'chrome.navigation_link' => 'Navigacija →',
    'chrome.menu_gone' => 'Izbornik koji ste odabrali više ne postoji pa je odabir poništen.',

    // "Button" alone read as a toggle or a section heading on the rendered screen, with
    // "Button address" directly under it. It is the words ON the button, so it says so.
    'chrome.button_label' => 'Tekst gumba',
    'chrome.button_label_hint' => 'Riječi na gumbu, primjerice „Javite nam se“. Kad odaberete stranicu, upisuje se njezin naslov; po želji ga promijenite. Bez teksta gumb se ne prikazuje.',
    'chrome.button_url' => 'Gumb vodi na',
    'chrome.button_url_hint' => 'Neobavezni gumb desno u zaglavlju, za ono što najviše želite da posjetitelji učine. Odaberite jednu od svojih stranica ili „Druga adresa…“ za poveznicu bilo kamo drugamo. Ostavite oboje prazno ako ne želite gumb.',
    'chrome.button_address' => 'Adresa gumba',
    'chrome.button_address_hint' => 'Upisuje se sama kad odaberete stranicu. Sve ostalo upišite ovdje: /kontakt, https://example.com, adresu e-pošte ili broj telefona. Adresa e-pošte otvara posjetiteljev program za e-poštu; broj telefona na mobitelu se može dodirnuti za poziv.',
    'chrome.button_url_refused' => 'Ta adresa nije prihvaćena pa nije spremljena.',

    'chrome.text' => 'Tekst',
    'chrome.text_hint' => 'Redak ili dva — tko ste i gdje vas se može naći. Podebljano, kurziv i poveznice; adresa e-pošte ili broj telefona kao poveznica otvara posjetiteljev program za e-poštu ili biranje broja.',
    'chrome.small_print' => 'Sitna slova',
    'chrome.small_print_hint' => 'Posljednji redak svake stranice: autorska prava, matični broj tvrtke, što god zakon traži.',

    'chrome.look' => 'Kako izgledaju',
    'chrome.look_intro' => 'Svaki odabir prati karakter dok ne odaberete nešto drugo. Boje dolaze iz palete pa svaka kombinacija ostaje čitljiva.',
    // On the Appearance screen the follow state is a segment and a badge (D-065): the
    // segment names what the character gives, the badge says the choice is still its.
    'chrome.look.follow' => 'Prema karakteru: :value',
    // Arrangement and behaviour are two choices since D-112.
    'chrome.look.header_arrangement' => 'Raspored zaglavlja',
    'chrome.look.header_arrangement.left' => 'Lijevo',
    'chrome.look.header_arrangement.inline' => 'U retku',
    'chrome.look.header_arrangement.centred' => 'Centrirano',
    'chrome.look.header_arrangement.split' => 'Razdvojeno',
    'chrome.look.header_arrangement.masthead' => 'Novinsko',
    'chrome.look.header_behaviour' => 'Ponašanje zaglavlja',
    'chrome.look.header_behaviour.static' => 'Pomiče se sa stranicom',
    'chrome.look.header_behaviour.sticky' => 'Uvijek na vrhu',
    'chrome.look.header_behaviour.over' => 'Preko naslovnog bloka',
    // The footer's columns (D-115).
    'chrome.footer_column' => 'Stupac :n',
    'chrome.footer_column_title' => 'Naslov',
    'chrome.footer_menu_same' => 'Izbornik zaglavlja',
    'chrome.footer_menu_hint' => 'Bez izbornika, ponovno izbornik zaglavlja ili vlastiti izbornik ovog stupca — Privatnost, Impresum, Uvjeti. Izrađuje se na dnu ove stranice i bira po nazivu, pa je na svakom jeziku izbornik tog naziva izbornik ovog stupca. Naslov i tekst stupca nalaze se niže, pod „Tekst podnožja“.',
    'chrome.footer_column_menu_hint' => 'Prikazuje se samo ako raspored podnožja u Izgledu prikazuje ovaj stupac.',
    'chrome.look.footer_layout' => 'Raspored podnožja',
    'chrome.look.footer_layout.simple' => 'Jedan stupac',
    'chrome.look.footer_layout.centred' => 'Centrirano',
    'chrome.look.footer_layout.columns' => 'Dva stupca',
    'chrome.look.footer_layout.menu_first' => 'Najprije izbornik',
    'chrome.look.footer_layout.three' => 'Tri stupca',
    'chrome.look.footer_edge' => 'Rub podnožja',
    'chrome.look.footer_edge.none' => 'Bez ruba',
    'chrome.look.footer_edge.line' => 'Crta',
    'chrome.look.footer_edge.slant' => 'Kosina',
    'chrome.look.footer_edge.curve' => 'Luk',
    'chrome.look.small_print_row' => 'Posljednji redak',
    'chrome.look.small_print_row.left' => 'Jedno ispod drugog',
    'chrome.look.small_print_row.split' => 'Razdvojeno',
    'chrome.look.small_print_row.centred' => 'Centrirano',
    'chrome.look.header_surface' => 'Podloga zaglavlja',
    'chrome.look.header_surface.plain' => 'Obična',
    'chrome.look.header_surface.tinted' => 'Obojena',
    'chrome.look.header_surface.contrast' => 'Kontrastna',
    'chrome.look.header_surface.gradient' => 'Prijelaz',
    'chrome.look.footer_surface' => 'Podloga podnožja',
    'chrome.look.footer_surface.plain' => 'Obična',
    'chrome.look.footer_surface.tinted' => 'Obojena',
    'chrome.look.footer_surface.contrast' => 'Kontrastna',
    'chrome.look.footer_surface.gradient' => 'Prijelaz',
    'chrome.look.header_edge' => 'Rub zaglavlja',
    'chrome.look.header_edge.none' => 'Bez ruba',
    'chrome.look.header_edge.line' => 'Crta',
    'chrome.look.header_edge.shadow' => 'Sjena',
    'chrome.look.brand' => 'Naziv web-stranice',
    'chrome.look.brand.logo' => 'Logotip',
    'chrome.look.brand.name' => 'Naziv',
    'chrome.look.brand.both' => 'Oboje',
    'chrome.look.nav_style' => 'Stil izbornika',
    'chrome.look.nav_style.plain' => 'Običan',
    'chrome.look.nav_style.caps' => 'Velika slova',
    'chrome.look.nav_style.pills' => 'Kapsule',
    'chrome.look.nav_style.bar' => 'Traka',
    'chrome.look.nav_style.chips' => 'Pločice',
    'chrome.look.nav_ink' => 'Boja izbornika',
    'chrome.look.nav_ink.accent' => 'Naglasak',
    'chrome.look.nav_ink.ink' => 'Tekst',
    'chrome.look.header_button' => 'Stil gumba',
    'chrome.look.header_button.filled' => 'Ispunjen',
    'chrome.look.header_button.outline' => 'Obrub',
    'chrome.look.header_button.text' => 'Poveznica',
    // Renamed (D-116's third round): "Footer menu columns: Three" read as the footer's three
    // columns, and the owner set it looking for them. It is how a long menu is listed.
    'chrome.look.footer_links' => 'Izbornici u stupcima',
    'chrome.look.footer_links.auto' => 'Prema rasporedu',
    'chrome.look.footer_links.list' => 'Jedna ispod druge',
    'chrome.look.footer_columns' => 'Dugi izbornik',
    'chrome.look.footer_columns.2' => 'Jedan popis',
    'chrome.look.footer_columns.3' => 'Dva popisa',
    'chrome.look.footer_columns.4' => 'Tri popisa',
    'chrome.look.logo_size' => 'Veličina logotipa',
    'chrome.look.logo_size.small' => 'Mala',
    'chrome.look.logo_size.medium' => 'Srednja',
    'chrome.look.logo_size.large' => 'Velika',

    'chrome.save' => 'Spremi zaglavlje i podnožje',
    'chrome.saved' => 'Zaglavlje i podnožje su spremljeni.',

    // Global decisions v2 (D-164).
    'chrome.look.header_opacity' => 'Neprozirnost pozadine',
    'chrome.look.header_blur' => 'Zamućenje pozadine',
    'chrome.look.header_height' => 'Visina zaglavlja',
    'chrome.look.header_height.compact' => 'Zbijeno',
    'chrome.look.header_height.normal' => 'Uobičajeno',
    'chrome.look.header_height.roomy' => 'Prozračno',
    'chrome.look.header_button.none' => 'Bez gumba',
];
