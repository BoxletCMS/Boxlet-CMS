<?php

// Your login (PLAN.md D-132): the admin's own email and password. A new concern, so a new
// file: t() merges every file in the locale's directory.

return [
    'account.title' => 'Podaci za prijavu',
    'account.intro' => 'Adresa i lozinka kojima se prijavljujete te prijava u dva koraka. Za promjenu adrese ili lozinke potrebna je vaša trenutna lozinka.',
    'account.email' => 'E-pošta',
    'account.email_label' => 'E-pošta za prijavu',
    'account.email_save' => 'Promijeni e-poštu',
    'account.email_invalid' => 'To nije adresa e-pošte. Ništa nije promijenjeno.',
    'account.email_changed' => 'Sada se prijavljujete kao :email.',
    'account.password' => 'Lozinka',
    'account.current' => 'Trenutna lozinka',
    'account.new' => 'Nova lozinka',
    'account.new_hint' => 'Najmanje :min znakova. Dugu rečenicu lakše je zapamtiti nego kratak niz nasumičnih znakova.',
    'account.confirm' => 'Ponovite novu lozinku',
    'account.password_save' => 'Promijeni lozinku',
    'account.password_changed' => 'Vaša lozinka je promijenjena.',
    'account.current_wrong' => 'To nije vaša trenutna lozinka. Ništa nije promijenjeno.',

    // A forgotten password (D-132).
    'account.forgot_link' => 'Zaboravili ste lozinku?',
    'account.forgot_title' => 'Zaboravljena lozinka',
    'account.forgot_intro' => 'Upišite adresu kojom se prijavljujete. Ako je to adresa administratora, na nju će stići poveznica za postavljanje nove lozinke.',
    'account.forgot_submit' => 'Pošalji poveznicu',
    'account.back_to_login' => 'Natrag na prijavu',
    'account.forgot_sent' => 'Ako je to adresa administratora, poveznica je na putu. Može se iskoristiti jednom, u roku od :minutes minuta. Ništa nije stiglo? Pogledajte u mapu neželjene pošte ili upotrijebite način opisan u nastavku.',
    'account.forgot_sent_one' => 'Ako je to adresa administratora, poveznica je na putu. Može se iskoristiti jednom, u roku od :minutes minute. Ništa nije stiglo? Pogledajte u mapu neželjene pošte ili upotrijebite način opisan u nastavku.',
    'account.forgot_sent_few' => 'Ako je to adresa administratora, poveznica je na putu. Može se iskoristiti jednom, u roku od :minutes minute. Ništa nije stiglo? Pogledajte u mapu neželjene pošte ili upotrijebite način opisan u nastavku.',
    'account.forgot_no_mail' => 'Ova web-stranica još ne može slati e-poštu, pa poveznicu nije moguće poslati. Umjesto toga upotrijebite način opisan u nastavku.',
    'account.forgot_ftp' => 'Bez e-pošte: putem FTP-a ili upravitelja datoteka kod svojeg pružatelja hostinga stavite na poslužitelj datoteku naziva :file, kojoj je jedini redak nova lozinka. Pri sljedećem otvaranju stranice za prijavu lozinka se postavlja, a datoteka briše.',
    'account.mail_subject' => 'Nova lozinka za :site',
    'account.mail_body' => "Netko je zatražio novu lozinku za administraciju web-stranice :site. Ako ste to bili vi, otvorite ovu poveznicu i odaberite novu lozinku:\n\n:url\n\nPoveznica se može iskoristiti jednom, u roku od :minutes minuta. Ako to niste bili vi, ne činite ništa: vaša lozinka ostaje nepromijenjena.",
    'account.mail_body_one' => "Netko je zatražio novu lozinku za administraciju web-stranice :site. Ako ste to bili vi, otvorite ovu poveznicu i odaberite novu lozinku:\n\n:url\n\nPoveznica se može iskoristiti jednom, u roku od :minutes minute. Ako to niste bili vi, ne činite ništa: vaša lozinka ostaje nepromijenjena.",
    'account.mail_body_few' => "Netko je zatražio novu lozinku za administraciju web-stranice :site. Ako ste to bili vi, otvorite ovu poveznicu i odaberite novu lozinku:\n\n:url\n\nPoveznica se može iskoristiti jednom, u roku od :minutes minute. Ako to niste bili vi, ne činite ništa: vaša lozinka ostaje nepromijenjena.",
    'account.reset_title' => 'Nova lozinka',
    'account.reset_submit' => 'Postavi novu lozinku',
    'account.reset_gone' => 'Ova poveznica više ne vrijedi: već je iskorištena ili je starija od sat vremena.',
    'account.reset_again' => 'Zatraži novu poveznicu',
    'account.reset_done' => 'Vaša nova lozinka je postavljena. Prijavite se njome.',
    'account.file_done' => 'Lozinka iz datoteke :file je postavljena, a datoteka je obrisana. Prijavite se njome.',
    'account.file_left' => 'Boxlet nije mogao obrisati datoteku :file, pa je nije upotrijebio. Obrišite je putem FTP-a i vratite je tek kad mapa dopusti Boxletu da je obriše.',
    'account.file_refused' => 'Lozinka iz datoteke :file nije upotrijebljena: :problem Datoteka je obrisana.',
];
