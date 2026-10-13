<?php

// Media: uploading, refusing, the library screen, and what one picture means. The
// library is "Media" because it will later hold documents to download (PLAN.md O-17);
// wording about a single image still says "picture", because that is what it is.
// (SPEC §5.5).
//
// Split from en.php, which had grown past the 300-line rule. t() loads every file in
// lang/, so a new one needs no registration — and a key defined twice in two files is a
// test failure rather than whichever file happened to load first.

return [
    // What a refusal says has to name the thing that was wrong: "invalid file" sends
    // someone back to try the same file again.
    'media.refused' => 'Boxlet ne prihvaća tu datoteku (izgleda kao :type). Slike: JPEG, PNG, WebP, GIF ili AVIF. Datoteke za preuzimanje: PDF, ZIP, Word, Excel, PowerPoint, OpenDocument, TXT ili CSV.',
    'media.refused_avif' => 'Ovaj poslužitelj ne može čitati slike AVIF. Spremite sliku kao JPEG ili PNG pa učitajte tu datoteku.',
    // Refused rather than accepted-and-broken. Without GD or Imagick nothing can be
    // generated, and the picture would sit in the library as a card with no thumbnail and
    // no way to fix it — a silent failure the owner would have to diagnose from the shape
    // of the damage. The installer reports this too (install.opt.images).
    'media.refused_no_encoder' => 'Ovaj poslužitelj uopće ne može obrađivati slike: nije instaliran ni GD ni Imagick, pa se ne mogu izraditi veličine. Ništa nije učitano. Zamolite pružatelja hostinga da uključi proširenje GD.',
    'media.refused_heic' => 'Slike HEIC ne prihvaćaju se jer ih većina poslužitelja ne može čitati. Najprije izvezite sliku kao JPEG — na iPhoneu: Postavke → Kamera → Formati → Najkompatibilnije.',
    'media.storage_unwritable' => 'Slika nije spremljena: u mapu storage/uploads nije moguće pisati. Ništa nije promijenjeno.',
    'media.too_large' => 'Ta je slika veća nego što ovaj poslužitelj prihvaća (:limit po datoteci). Ništa nije učitano.',
    'media.incomplete_upload' => 'Učitavanje nije dovršeno — veza se prekinula usred prijenosa. Ništa nije spremljeno; pokušajte ponovno.',
    'media.no_file' => 'Nije odabrana nijedna datoteka.',

    // Said in the browser, before anything is sent. A body over the server's limit never
    // reaches a page of ours, so this is the only place it can be explained.
    'media.too_large_named' => 'Datoteka :name ima :size, a to je više nego što ovaj poslužitelj prihvaća (:limit po slici). Nije učitana.',
    'media.too_large_total' => 'Te slike zajedno imaju :size, a ovaj poslužitelj prihvaća :limit po učitavanju. Pošaljite ih u manjim skupinama.',
    'media.limits' => 'Slike (JPEG, PNG, WebP, GIF, AVIF) i datoteke za preuzimanje (PDF, ZIP, Word, Excel, PowerPoint, OpenDocument, TXT, CSV) · do :file po datoteci, :request odjednom',
    // Files for visitors to download (PLAN.md D-126).
    'media.kind' => 'Slike ili datoteke',
    'media.kind.all' => 'Sve',
    'media.kind.pictures' => 'Slike',
    'media.kind.files' => 'Datoteke',
    'media.downloads_one' => ':count preuzimanje',
    'media.downloads_few' => ':count preuzimanja',
    'media.downloads_many' => ':count preuzimanja',
    'media.file_details' => 'Datoteka koju posjetitelji mogu preuzeti. Nikad se ne prikazuje kao stranica: tko god otvori njezinu adresu, dobiva ponudu da je spremi.',
    'media.file_address' => 'Adresa za preuzimanje',
    'media.file_downloads' => 'Preuzeto',
    'media.file_try' => 'Preuzmi',
    'media.file_used_by_none' => 'Nijedna stranica još ne nudi ovu datoteku.',

    // The library screen.
    'media.title' => 'Mediji',
    // The library as a table (D-052).
    'media.lede' => 'Svaka se slika čuva u nekoliko veličina, izrađenih pri učitavanju. Opisi se čitaju posjetiteljima koji ne mogu vidjeti sliku. Ovdje su i datoteke koje posjetitelji mogu preuzeti — PDF-ovi, dokumenti, arhive.',
    'media.drop_anywhere' => 'Ispustite datoteke bilo gdje na ovaj zaslon ili ih',
    'media.show' => 'Prikaži',
    'media.show.all' => 'Sve',
    'media.show.unused' => 'Nekorišteno',
    'media.show.undescribed' => 'Bez opisa',
    'media.show.none' => 'Uz ovaj filtar nije ostala nijedna slika.',
    'media.show.every' => 'Prikaži sve slike',
    'media.count' => ':count datoteka · :size',
    'media.count_one' => ':count datoteka · :size',
    'media.count_few' => ':count datoteke · :size',
    'media.col.picture' => 'Slika',
    'media.col.file' => 'Datoteka',
    'media.col.dimensions' => 'Dimenzije',
    'media.col.size' => 'Veličina',
    'media.col.used' => 'Gdje se koristi',
    'media.col.described' => 'Opis',
    'media.col.actions' => 'Radnje',
    'media.used_one' => ':count stranica',
    'media.used_few' => ':count stranice',
    'media.used_many' => ':count stranica',
    'media.used_site' => 'web-stranica',
    'media.unused_hint' => 'Nijedna stranica ne prikazuje ovu sliku, a nije ni logotip web-stranice, ni njezina ikona, ni slika za dijeljenje.',
    'media.described.missing' => 'Nedostaje',
    'media.described.set' => 'Upisan',
    'media.unfinished' => 'Nedovršeno',
    'media.more' => 'Više radnji za :name',
    'media.open' => 'Otvori',
    'media.empty' => 'Ovdje još nema ničega. Za početak učitajte sliku.',
    'media.upload' => 'Učitaj datoteke',
    'media.upload_hint' => 'Dodajte slike tako da ih povučete ovamo, i više njih odjednom, ili kliknite da ih odaberete. Svaka se pri učitavanju prilagođava za mobitele i zaslone, a njezin opis za posjetitelje koji je ne mogu vidjeti popunjava se iz same slike ili iz naziva datoteke.',
    'media.drop' => 'Povucite slike ovamo ili ih',
    'media.browse' => 'odaberite',
    'media.uploading' => 'Učitavanje…',
    'media.search_placeholder' => 'Tražite po nazivu ili opisu',
    'media.pager' => 'Stranice biblioteke',
    'media.newer' => 'Novije',
    'media.older' => 'Starije',
    'media.page_of' => 'Stranica :page od :pages · ukupno :total',
    'media.upload_submit' => 'Učitaj',
    'media.uploaded' => 'Učitano: :count.',
    'media.duplicate' => 'Datoteka :name već je u biblioteci, pa nije spremljena dvaput.',
    'media.search' => 'Tražite po nazivu ili opisu',
    'media.search_submit' => 'Traži',
    'media.search_none' => 'Nijedna slika ne odgovara pojmu „:term“.',
    'media.no_thumb' => 'Još se obrađuje',
    'media.incomplete' => 'Još nisu izrađene sve veličine.',
    'media.finish' => 'Dovrši obradu',
    'media.finished' => 'Dovršeno: :name.',
    'media.dimensions' => ':width × :height',
    'media.not_found' => 'Ta slika više ne postoji.',
    // The picker says why rather than showing an empty panel, which would read as
    // "there are no pictures" when the truth is that the list could not be fetched.
    'media.pick_failed' => 'Slike se nisu mogle učitati. Zatvorite ovo i pokušajte ponovno.',
    // The picker's resting state has to say what pressing it does. "office" alone read as
    // a text field somebody had typed into.
    'media.pick_choose' => 'Odaberi sliku',
    'media.pick_change' => 'Promijeni',
    'media.pick_close' => 'Zatvori',

    // One picture.
    'media.back' => 'Svi mediji',
    'media.details' => 'Pojedinosti',
    'media.original_name' => 'Učitano kao',
    'media.dimensions_label' => 'Dimenzije',
    'media.size' => 'Veličina datoteke',
    'media.format' => 'Format',
    'media.added' => 'Dodano',
    'media.used_by' => 'Koristi se na',
    'media.used_by_none' => 'Nijedna stranica još ne koristi ovu sliku.',
    'media.meaning' => 'Što prikazuje ova slika',
    'media.save' => 'Spremi',
    'media.delete' => 'Obriši',
    'media.delete_confirm' => 'Obrisati :name? To se ne može poništiti.',
    'media.deleted' => 'Slika je obrisana.',
    // Several deleted together (D-218).
    'media.bulk.all' => 'Odaberi sve na ovoj stranici popisa',
    'media.bulk.pick' => 'Odaberi :name',
    'media.bulk.none_ticked' => 'Označite slike i datoteke da biste ih obrisali više odjednom.',
    'media.bulk.ticked' => 'Odabrano: :count',
    'media.bulk.delete' => 'Obriši odabrano',
    'media.bulk.confirm_any' => 'Obrisati odabrane slike i datoteke? To se ne može poništiti.',
    'media.bulk.confirm' => 'Obrisati odabrane slike i datoteke (:count)? One koje neka stranica još prikazuje bit će zadržane. To se ne može poništiti.',
    'media.bulk.none' => 'Ništa nije odabrano, pa ništa nije obrisano.',
    'media.bulk.deleted_one' => 'Obrisana je :count datoteka.',
    'media.bulk.deleted_few' => 'Obrisane su :count datoteke.',
    'media.bulk.deleted_many' => 'Obrisano je :count datoteka.',
    'media.bulk.kept_heading_one' => 'Zadržana je :count datoteka jer je neka stranica još prikazuje:',
    'media.bulk.kept_heading_few' => 'Zadržane su :count datoteke jer ih stranice još prikazuju:',
    'media.bulk.kept_heading_many' => 'Zadržano je :count datoteka jer ih stranice još prikazuju:',
    'media.bulk.kept_one' => ':name (stranice: :pages)',
    'media.deleted_file' => 'Datoteka je obrisana.',
    // A refusal that does not say WHICH pages sends someone hunting through the site.
    'media.in_use' => 'Još se koristi na stranicama: :pages. Najprije je ondje uklonite, a zatim je obrišite.',
    'media.replace_drop' => 'Ispustite novu sliku ovamo ili je',
    'media.replace_submit' => 'Zamijeni sliku',
    'media.replace' => 'Zamijeni',
    'media.replace_hint' => 'Stavite drugu sliku na njezino mjesto. Svaka stranica koja je koristi prikazat će novu, a veličine se izrađuju ponovno.',
    'media.replaced' => 'Slika je zamijenjena.',
    // :file is filled in by the browser with the dropped file's name.
    'media.replace_confirm' => 'Zamijeniti „:name“ datotekom :file? Time se slika mijenja na svakoj stranici koja je prikazuje (broj stranica: :count), a staru sliku nije moguće vratiti.',
    'media.replace_confirm_unused' => 'Zamijeniti „:name“ datotekom :file? Staru sliku nije moguće vratiti.',
    'media.replace_duplicate' => 'Potpuno ista datoteka već je u biblioteci, kao :name. Ništa nije zamijenjeno.',
    // Cropping (D-026). The two buttons are deliberately not symmetrical: one is safe and
    // one cannot be undone, and the wording has to carry that difference on its own,
    // because a confirm dialog is the last thing read and the first thing dismissed.
    'media.crop' => 'Izreži',
    'media.crop_hint' => 'Odaberite dio slike koji želite zadržati. Veličine se ponovno izrađuju iz onoga što zadržite.',
    'media.crop_open' => 'Izreži',
    // The focal point (PLAN.md D-121).
    'media.focal' => 'Žarište',
    'media.focal_hint' => 'Kliknite dio slike koji mora ostati vidljiv. Gdje god se slika izreže u neki oblik — iza teksta naslovnog bloka, kao pozadina sekcije, u kvadratu — ta točka ostaje u kadru.',
    'media.focal_x' => 'Vodoravno (%)',
    'media.focal_y' => 'Okomito (%)',
    'media.focal_save' => 'Spremi žarište',
    'media.focal_saved' => 'Žarište je pomaknuto, a izrezane veličine ponovno su izrađene.',
    'media.focal_saved_later' => 'Žarište je pomaknuto. Izrezane veličine ponovno se izrađuju — dovršit će ih zaslon Mediji.',
    'media.focal_wide' => 'U širokom prostoru, kao na računalu',
    'media.focal_tall' => 'U visokom prostoru, kao na mobitelu',
    'media.crop_cancel' => 'Odustani',
    'media.crop_ratio' => 'Oblik',
    'media.crop_ratio_free' => 'Slobodan',
    'media.crop_ratio_hero' => 'Široki banner (16:9)',
    'media.crop_ratio_card' => 'Kartica (3:2)',
    'media.crop_ratio_wide' => 'Dijeljenje (1,91:1)',
    'media.crop_ratio_thumb' => 'Kvadrat (1:1)',
    'media.crop_new' => 'Spremi kao novu sliku',
    'media.crop_new_hint' => 'Ova slika ostaje kakva jest, a izrezani dio dodaje se kao nova slika.',
    'media.crop_replace' => 'Zamijeni ovu sliku',
    'media.crop_replace_hint' => 'Svaka stranica koja koristi ovu sliku prikazat će izrezanu inačicu. Izvornik nestaje i to se ne može poništiti — odaberite „Spremi kao novu sliku“ ako ga želite zadržati.',
    'media.crop_replace_confirm' => 'Zamijeniti :name izrezanom inačicom? Izvornik nije moguće vratiti.',
    'media.cropped_new' => 'Izrezana slika je dodana.',
    'media.cropped_replaced' => 'Slika je zamijenjena izrezanom inačicom.',
    'media.crop_too_small' => 'Izrezani dio ima manje od :min piksela na kraćoj strani. Odaberite veće područje.',
    'media.crop_outside' => 'Izrezani dio izlazi izvan slike. Ništa nije promijenjeno.',
    'media.crop_ratio_wrong' => 'Izrezani dio ne odgovara odabranom obliku. Ništa nije promijenjeno.',

    'media.alt' => 'Opis slike',
    'media.alt_hint' => 'Što slika prikazuje, za nekoga tko je ne može vidjeti. Ostavite prazno ako je slika samo ukras.',
    // A guess is marked as one until the owner looks at it. Saying "check it" rather than
    'media.caption' => 'Potpis',
    'media.meta_saved' => 'Spremljeno.',
    'media.remake_title' => 'Ponovno izradi sve veličine',
    'media.remake_intro' => 'Svaka se slika čuva u nekoliko veličina, izrađenih pri učitavanju. Ponovnom izradom starije slike dobivaju ono što ova web-stranica izrađuje sada — od posljednjeg ažuriranja manje datoteke — a potrebna je i kad se promijene same veličine. Slike cijelo vrijeme ostaju na vašim stranicama; nove datoteke zamjenjuju stare jednu po jednu.',
    'media.remake_start' => 'Ponovno izradi sve veličine',
    'media.remake_confirm' => 'Ponovno izraditi veličine svih slika? S mnogo slika to može potrajati nekoliko minuta. Vaše ih stranice cijelo vrijeme prikazuju.',
    'media.remake_started' => 'Slike za ponovnu izradu: :count. Postupak teče sam dok je ova stranica otvorena.',
    'media.remake_left' => 'Slike koje još treba ponovno izraditi: :left.',
    'media.remake_continue' => 'Nastavi',
    'media.remake_auto' => 'Postupak se nastavlja sam. Možete napustiti ovu stranicu; kad se vratite, postupak se nastavlja.',
    'media.remake_progress' => 'Još za ponovnu izradu: :left.',
    'media.remake_done' => 'Veličine svih slika ponovno su izrađene.',
];
