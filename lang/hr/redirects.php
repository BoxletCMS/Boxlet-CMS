<?php

// Redirects (PLAN.md D-129): addresses that lead elsewhere. A new concern, so a new file.

return [
    'redirects.title' => 'Preusmjeravanja',
    'redirects.intro' => 'Adrese koje više nemaju vlastitu stranicu i kamo umjesto toga vode. Posjetitelj ili tražilica koji dođu na takvu adresu trajno se preusmjeravaju dalje (301).',

    'redirects.rules' => 'Vaša pravila',
    'redirects.rules_none' => 'Još nema pravila. Dodajte po jedno za svaku adresu stare web-stranice koju ljudi ili tražilice još koriste.',
    'redirects.col.from' => 'Adresa',
    'redirects.col.to' => 'Vodi na',
    'redirects.col.used' => 'Korišteno',
    'redirects.col.actions' => 'Radnje',
    'redirects.col.old' => 'Stara adresa',
    'redirects.col.page' => 'Stranica',
    'redirects.nowhere' => 'Njezina je stranica obrisana',
    'redirects.unpublished' => 'Stranica nije objavljena',
    'redirects.never_used' => 'Još nije',
    'redirects.used_one' => ':count put, zadnji put :date',
    'redirects.used_few' => ':count puta, zadnji put :date',
    'redirects.used_many' => ':count puta, zadnji put :date',
    'redirects.delete' => 'Obriši',
    'redirects.delete_confirm' => 'Obrisati preusmjeravanje za :from? Ta će adresa tada javljati „nije pronađeno“.',

    'redirects.new' => 'Novo pravilo',
    'redirects.from' => 'Stara adresa',
    'redirects.from_hint' => 'Onako kako je glasila na staroj web-stranici: /usluge.html, /index.php?id=12 ili cijela adresa. Velika i mala slova nisu važna. Neki poslužitelji sami odgovaraju na adrese koje završavaju s .php, osim /index.php, i one nikad ne stignu do Boxleta.',
    'redirects.to_page' => 'Vodi na stranicu',
    'redirects.to_page_none' => '— Odaberite stranicu —',
    'redirects.draft' => 'skica',
    'redirects.or' => 'ili',
    'redirects.to_url' => 'Vodi na adresu',
    'redirects.to_url_hint' => 'Druga web-stranica ili putanja na ovoj. Ako stranica postoji, bolje je odabrati nju: preusmjeravanje tada radi i nakon što se stranica preimenuje.',
    'redirects.create' => 'Dodaj pravilo',
    'redirects.created' => 'Pravilo za :from je dodano.',
    'redirects.deleted' => 'Preusmjeravanje za :from je obrisano.',

    'redirects.from_required' => 'Upišite staru adresu. Početna stranica ne može se preusmjeriti.',
    'redirects.from_system' => 'Ova adresa pripada samom Boxletu i ne može se preusmjeriti.',
    'redirects.from_taken' => 'Za ovu adresu već postoji pravilo.',
    'redirects.from_live' => 'Ovo je adresa stranice „:page“, koja na nju odgovara prva, pa se pravilo nikad ne bi primijenilo.',
    'redirects.to_required' => 'Odaberite stranicu ili upišite adresu.',
    'redirects.to_both' => 'Odaberite stranicu ili upišite adresu, ne oboje.',
    'redirects.url_invalid' => 'Upišite adresu koja počinje s /, http:// ili https://.',

    'redirects.kept' => 'Stare adrese preimenovanih stranica',
    'redirects.kept_intro' => 'Boxlet ih čuva kad objavljena stranica dobije novu adresu, kako bi poveznice na staru i dalje radile. Obrišite neku samo ako nitko više ne bi smio doći do stranice tim putem.',
    'redirects.kept_none' => 'Još nijedna. Kad promijenite adresu objavljene stranice, stara će se pojaviti ovdje.',
];
