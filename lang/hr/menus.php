<?php

// Menus (PLAN.md D-028). A new concern, so a new file: t() merges every file in the
// locale's directory and a key defined twice is a test failure, not a silent winner.

return [
    'menus.title' => 'Izbornici',
    'menus.intro' => 'Što se nudi posjetiteljima i kako se to ondje zove. Izbornik pripada jednom jeziku.',
    'menus.none' => 'Još nema izbornika.',

    'menus.new' => 'Novi izbornik',
    'menus.name' => 'Naziv',
    'menus.name_hint' => 'Ovaj naziv vidite samo vi, nikad posjetitelji: nazovite ga Zaglavlje, Podnožje ili kako god vam govori gdje stoji. Zaglavlje i podnožje po njemu biraju izbornik.',
    'menus.name_required' => 'Upišite naziv izbornika.',
    'menus.name_taken' => 'Na ovom jeziku već postoji izbornik s tim nazivom.',
    'menus.locale' => 'Jezik',
    'menus.create' => 'Izradi izbornik',
    'menus.rename' => 'Preimenuj izbornik',
    'menus.name_edit_hint' => 'Ovaj naziv vidite samo vi; posjetitelji nikad. Zaglavlje i podnožje po njemu pronalaze svoj izbornik i prate ga kad ga preimenujete.',
    'menus.created' => 'Izbornik je izrađen.',
    'menus.renamed' => 'Izbornik je preimenovan.',
    'menus.deleted' => 'Izbornik je obrisan.',
    'menus.delete' => 'Obriši',
    'menus.delete_confirm' => 'Želite li obrisati izbornik „:name“ i sve u njemu?',
    'menus.not_found' => 'Tog izbornika ovdje nema.',

    'menus.col.name' => 'Izbornik',
    'menus.col.locale' => 'Jezik',
    'menus.col.items' => 'Stavke',
    'menus.col.order' => 'Redoslijed',
    'menus.col.label' => 'Prikazuje se kao',
    'menus.col.target' => 'Vodi na',
    'menus.col.actions' => 'Radnje',
    'menus.items_count' => ':count stavki',
    'menus.items_count_one' => ':count stavka',
    'menus.items_count_few' => ':count stavke',

    'menus.item.add' => 'Dodaj stavku',
    'menus.item.added' => 'Stavka je dodana.',
    'menus.item.saved' => 'Stavka je spremljena.',
    'menus.item.deleted' => 'Stavka je uklonjena.',
    'menus.item.page' => 'Stranica',
    'menus.item.page_none' => 'Bez stranice — unesi adresu',
    'menus.item.url' => 'Adresa',
    'menus.item.page_hint' => 'Stranica koju ova stavka otvara. Kad je odaberete, ispod se upisuju njezina adresa i naslov. Ako se adresa stranice kasnije promijeni, stavka je prati.',
    'menus.item.url_hint' => 'Upisuje se kad odaberete stranicu. Za sve ostalo — drugu web-stranicu, adresu e-pošte, broj telefona — ostavite stranicu praznom i upišite to ovdje. Adresa e-pošte otvara posjetiteljevu aplikaciju za e-poštu, a broj telefona na mobitelu se može dodirnuti za poziv.',
    'menus.item.url_refused' => 'Ta adresa nije prihvaćena, pa stavka zasad ne vodi nikamo.',
    'menus.item.label' => 'Prikazuje se kao',
    'menus.item.label_hint' => 'Riječi koje posjetitelji vide u izborniku. Odabrana stranica ovdje upisuje svoj naslov; skratite ga ako izbornik postane pretrpan. Ako ostane prazno, koristi se naslov same stranice.',
    'menus.item.edit' => 'Uredi',
    'menus.item.save' => 'Spremi stavku',
    'menus.item.edit_title' => 'Uredi „:label“',
    'menus.item.cancel' => 'Odustani',
    'menus.item.parent' => 'Nadređena stavka',
    'menus.item.parent_top' => 'Najviša razina',
    'menus.item.parent_invalid' => 'Ta stavka ne može imati drugu stavku ispod sebe.',
    'menus.item.needs_target' => 'Odaberite stranicu ili upišite adresu.',

    // An item whose page was deleted, or whose address was refused. Shown here and left
    // out of the site, so the owner can repoint it rather than hunt for what disappeared.
    'menus.item.broken' => 'Ne vodi nikamo',
    'menus.item.broken_hint' => 'Njezina je stranica obrisana ili njezina adresa nije prihvaćena. Posjetitelji je ne vide.',
    'menus.item.draft' => 'Stranica je skica',
    'menus.item.draft_hint' => 'Posjetitelji ne vide ovu stavku dok se stranica ne objavi.',

    'menus.order_hint' => 'Posjetitelji vide stavke ovim redoslijedom. Povucite redak za ručicu ili upotrijebite strelice. Stavka smještena pod drugu otvara se kao njezin podizbornik; izbornik ima samo jednu razinu podizbornika.',
    'menus.reordered' => 'Redoslijed je spremljen.',
    'menus.reorder_failed' => 'Taj redoslijed nije moguće spremiti. Osvježite stranicu i pokušajte ponovno.',
    'menus.move_up' => 'Pomakni gore',
    'menus.move_down' => 'Pomakni dolje',
];
