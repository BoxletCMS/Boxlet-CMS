<?php

// Admin and installer UI strings. Every string shown in the admin comes from here via
// t('key'); :name placeholders are replaced by t()'s second argument.
return [
    'csrf.invalid' => 'Ovaj je obrazac istekao. Vratite se, osvježite stranicu i pokušajte ponovno.',
    // Not a CSRF failure, though it arrives looking like one: PHP discards a post over
    // post_max_size whole, token and all. Saying the form expired would send someone to
    // reload and send the same oversized file again.
    'post.too_large' => 'To je veće od onoga što ovaj poslužitelj prima (:limit po zahtjevu), pa ništa od toga nije stiglo. Ništa nije spremljeno.',

    'auth.title' => 'Prijava',
    'auth.email' => 'E-pošta',
    'auth.password' => 'Lozinka',
    'auth.submit' => 'Prijavi se',
    'auth.failed' => 'Adresa e-pošte ili lozinka nije točna.',
    'auth.throttled' => 'Previše pokušaja prijave. Pokušajte ponovno za :minutes minuta.',
    'auth.throttled_one' => 'Previše pokušaja prijave. Pokušajte ponovno za :minutes minutu.',
    'auth.throttled_few' => 'Previše pokušaja prijave. Pokušajte ponovno za :minutes minute.',
    'auth.session_ended' => 'Vaša je prijava istekla: neko vrijeme nije bila korištena ili je lozinka promijenjena. Prijavite se ponovno za nastavak.',

    'admin.brand' => 'Boxlet',
    // Top left of the rail (D-144), in place of the site's name, which could be several words.
    'admin.control_panel' => 'Upravljačka ploča',
    'admin.skip' => 'Preskoči na sadržaj',
    'admin.nav.label' => 'Navigacija administracije',
    'admin.nav.dashboard' => 'Pregled',
    // The rail's groups (D-052).
    'admin.nav.group.site' => 'Web-stranica',
    'admin.nav.group.content' => 'Sadržaj',
    'admin.nav.group.presentation' => 'Prikaz',
    'admin.nav.group.administration' => 'Administracija',
    'admin.your_login' => 'Podaci za prijavu',
    'admin.rail.fold' => 'Sažmi izbornik na ikone',
    'admin.rail.open' => 'Otvori izbornik',
    'admin.site_time' => 'Vremenska zona web-stranice i trenutačno vrijeme u njoj',
    // The theme switch in the strip (D-054). The hint answers the fear the control raises:
    // a button in the admin that changes colours could be changing the site's.
    'admin.appearance' => 'Izgled',
    'admin.appearance_light' => 'Svijetli',
    'admin.appearance_dark' => 'Tamni',
    'admin.appearance_system' => 'Prema sustavu',
    'admin.appearance_hint' => 'Svijetli ili tamni. Mijenja samo to kako vama izgleda administracija, nikad web-stranicu.',
    'admin.nav.pages' => 'Stranice',
    'admin.nav.media' => 'Mediji',
    'admin.nav.appearance' => 'Izgled',
    'admin.nav.menus' => 'Navigacija',
    'admin.nav.forms' => 'Obrasci',
    'admin.nav.snippets' => 'Isječci',
    'admin.nav.statistics' => 'Statistika',
    'admin.nav.design_style' => 'Karakter i boje',
    'admin.nav.open' => 'Izbornik',
    'admin.nav.settings' => 'Postavke',
    'admin.nav.redirects' => 'Preusmjeravanja',
    'admin.nav.backups' => 'Sigurnosne kopije',
    'admin.nav.updates' => 'Ažuriranja',
    'admin.logout' => 'Odjavi se',
    'admin.view_site' => 'Pogledaj web-stranicu',

    'admin.dashboard.maintenance' => 'Način održavanja je uključen: posjetitelji umjesto web-stranice vide kratku poruku.',
    'admin.dashboard.maintenance_link' => 'Isključite ga u Postavkama',

    'richtext.toolbar' => 'Oblikovanje teksta',
    'richtext.bold' => 'Podebljano',
    'richtext.italic' => 'Kurziv',
    'richtext.link' => 'Poveznica',
    'richtext.unlink' => 'Ukloni poveznicu',
    'richtext.url' => 'Adresa',
    'richtext.url_placeholder' => 'https://example.com, adresa e-pošte ili broj telefona',
    'richtext.page' => 'Stranica',
    'richtext.link_text' => 'Tekst poveznice',
    'richtext.heading_2' => 'Naslov 2',
    'richtext.heading_3' => 'Naslov 3',
    'richtext.heading_4' => 'Naslov 4',
    'richtext.quote' => 'Citat',
    'richtext.bullets' => 'Popis s oznakama',
    'richtext.numbers' => 'Numerirani popis',
    // A paragraph's alignment (D-217): the first is no alignment of its own, the block's.
    'richtext.align' => 'Poravnanje',
    'richtext.align_left' => 'Poravnaj prema bloku',
    'richtext.align_center' => 'Centriraj',
    'richtext.align_right' => 'Poravnaj desno',
    'richtext.undo' => 'Poništi',
    'richtext.redo' => 'Ponovi',
    'richtext.plain' => 'Uredi kao HTML',
    'richtext.rich' => 'Uredi kao oblikovani tekst',
    'richtext.paste_plain' => 'Ctrl+Shift+V lijepi bez oblikovanja.',
];
