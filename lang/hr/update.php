<?php

// Updating an existing install, and maintenance mode (PLAN.md D-019, D-021).
//
// Split from en.php, which had grown past the 300-line rule. t() loads every file in
// lang/, so a new one needs no registration — and a key defined twice in two files is a
// test failure rather than whichever file happened to load first.

return [
    // Updating an existing install (PLAN.md D-019). Nothing runs on its own: the admin
    // sees one screen with one button, and the public site waits.
    'update.title' => 'Potrebno je ažuriranje baze podataka',
    'update.intro' => 'Ova kopija Boxleta je ažurirana, ali njezina baza podataka još nije usklađena. Dok ne pokrenete ažuriranje, web-stranica posjetiteljima prikazuje kratku stranicu „Ažuriranje u tijeku“.',
    'update.pending' => 'Čeka primjenu:',
    'update.run' => 'Pokreni ažuriranje',
    'update.backup_sqlite' => 'Vaša baza podataka je datoteka, a njezina se kopija sprema u storage/backups/ prije nego što se išta pokrene.',
    'update.backup_mysql' => 'Prije nego što pritisnete gumb, izradite sigurnosnu kopiju kod svojeg pružatelja hostinga. Boxlet je za MySQL još ne može izraditi.',
    'update.done' => 'Baza podataka je ažurna.',
    // The heading above it. Without a separate line the screen said "The database is up
    // to date." twice, as its own title and as its only sentence — noticed on the first
    // real use, on the live site.
    'update.up_to_date_title' => 'Baza podataka',
    'update.applied' => 'Primijenjeno: :files',
    'update.failed' => 'Ažuriranje je zaustavljeno kod datoteke :file. Ništa nakon nje nije pokrenuto, a sve prije nje je zabilježeno, pa kad otklonite problem i ponovno pritisnete gumb, ažuriranje se nastavlja od tog mjesta.',
    'update.locked' => 'Ažuriranje je već u tijeku. Pričekajte da završi, a zatim ponovno učitajte ovu stranicu.',
    'update.backup_failed' => 'Sigurnosnu kopiju nije bilo moguće zapisati u storage/backups/. Ništa nije promijenjeno. Omogućite pisanje u tu mapu i pokušajte ponovno.',

    // Maintenance mode (PLAN.md D-021). The same page and the same gate as an update;
    // the difference is that the owner chose it, and can still see the real site.
    'maintenance.title' => 'Način održavanja',
    'maintenance.off_now' => 'Vaša web-stranica vidljiva je svima.',
    'maintenance.on_now' => 'Posjetitelji vide kratku stranicu „vraćamo se uskoro“. Vi i dalje vidite samu web-stranicu.',
    'maintenance.turn_on' => 'Uključi način održavanja',
    'maintenance.turn_off' => 'Isključi način održavanja',
    'maintenance.turned_on' => 'Način održavanja je uključen. Posjetitelji vide kratku stranicu „vraćamo se uskoro“.',
    'maintenance.turned_off' => 'Način održavanja je isključen. Vaša web-stranica ponovno je vidljiva.',
    'maintenance.bar' => 'Način održavanja je uključen — posjetitelji ne vide ovu web-stranicu.',
    'maintenance.bar_off' => 'Isključi',
    'maintenance.failed' => 'Način održavanja nije bilo moguće promijeniti: u mapu storage nije moguće pisati. Vaša web-stranica nije promijenjena.',
];
