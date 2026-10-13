<?php

// Site settings (PLAN.md D-028). The maintenance strings stay in update.php, where the
// rest of D-021's vocabulary lives — only the switch moved screens, not its words.

return [
    'settings.title' => 'Postavke web-stranice',
    'settings.intro' => 'Kako se ova web-stranica zove, prema kojem vremenu radi, slike koje je predstavljaju i što posjetitelji vide dok je zatvorena zbog održavanja.',

    'settings.site_name' => 'Naziv web-stranice',
    'settings.site_name_hint' => 'Vlastiti naziv web-stranice. Prikazuje se na vrhu administracije i koristi se ondje gdje stranica treba navesti web-stranicu kojoj pripada. Ako ostane prazno, administracija prikazuje Boxlet.',

    'settings.timezone' => 'Vremenska zona',
    'settings.credit' => 'Prikaži „Izrađeno u Boxlet CMS-u“ u podnožju web-stranice',
    'settings.credit_hint' => 'Jedan nenametljiv redak u sitnom tisku, s poveznicom na boxletcms.org. Isključeno je, osim ako to želite: ono što vaši posjetitelji čitaju pripada vama, a ne Boxletu.',
    'settings.timezone_hint' => 'Vremenska zona u kojoj se nalazi vaša web-stranica. Datumi u administraciji — kad je stranica zadnji put uređena, kad je dodana slika — prikazuju se u toj zoni.',
    'settings.timezone_invalid' => 'To nije vremenska zona koju ovaj poslužitelj poznaje. Ništa nije spremljeno.',

    'settings.branding' => 'Vizualni identitet',
    'settings.general' => 'Općenito',
    'settings.sections' => 'Odjeljci postavki',
    'settings.last_saved' => 'Zadnje spremljeno: :when',
    'settings.branding_intro' => 'Slike koje predstavljaju vašu web-stranicu: u njezinu zaglavlju, na kartici preglednika i kad netko podijeli poveznicu na nju.',
    'settings.logo' => 'Logotip',
    'settings.logo_hint' => 'Prikazuje se gore lijevo na svakoj stranici, s poveznicom na početnu stranicu. Zadržava vlastiti oblik — širok logotip ostaje širok — a njegova se veličina bira pod Izgled → Zaglavlje i podnožje. Najbolje služi PNG ili SVG slika s prozirnom pozadinom.',
    'settings.logo_dark' => 'Logotip za tamne podloge',
    'settings.logo_dark_hint' => 'Neobavezno. Inačica logotipa za tamno zaglavlje — na kontrastnoj podlozi, prijelazu ili zaglavlju položenom preko tamne prve sekcije. Ondje se prikazuje umjesto gornjeg logotipa; ostavite prazno ako je logotip čitljiv na objema.',
    'settings.favicon' => 'Favicon',
    'settings.favicon_hint' => 'Mala ikona koju preglednik prikazuje na kartici i u oznakama, a mobitel na početnom zaslonu. Upotrijebite kvadratnu sliku; prikazuje se u veličini od 200×200 piksela i manjoj.',
    'settings.share_image' => 'Zadana slika za dijeljenje',
    'settings.share_image_hint' => 'Slika koja se prikazuje kad netko podijeli poveznicu na vašu web-stranicu na društvenim mrežama ili u aplikaciji za razmjenu poruka. Najbolje služi položena slika od oko 1200×630 piksela.',

    // No settings.maintenance heading: it said exactly what maintenance.title in update.php
    // already says, and that one lost its last caller when the switch left the dashboard.
    // Two keys with one wording is how they drift into two different words.
    'settings.maintenance_intro' => 'Način održavanja zatvara web-stranicu za posjetitelje dok na njoj radite: umjesto vaših stranica vide kratku poruku, a tražilicama se poručuje da navrate kasnije. Dok ste prijavljeni, vi i dalje vidite cijelu web-stranicu.',
    'settings.maintenance_message' => 'Poruka posjetiteljima dok je web-stranica zatvorena',
    'settings.maintenance_message_hint' => 'Što posjetitelji čitaju umjesto vaše web-stranice dok je uključen način održavanja, npr. „Ažuriramo web-stranicu i vraćamo se poslijepodne.“ Ostavite prazno za standardni tekst prikazan sivom bojom.',
    'settings.maintenance_message_save' => 'Spremi poruku',
    'settings.maintenance_message_saved' => 'Poruka o održavanju je spremljena.',

    'settings.save' => 'Spremi postavke',
    'settings.saved' => 'Postavke su spremljene.',
    'settings.picture_gone' => 'Jedna od odabranih slika više nije u biblioteci, pa je to polje ispražnjeno.',
];
