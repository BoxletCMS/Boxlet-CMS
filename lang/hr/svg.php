<?php

// An SVG logo, checked and cleaned on upload (PLAN.md D-142).
return [
    'svg.or' => 'Ili SVG',
    'svg.hint' => 'Oštar u svakoj veličini. Izvezite ga s tekstom pretvorenim u obrise: prikazan kao slika, SVG ne može koristiti fontove web-stranice i tekst iscrtava nekim drugim fontom. Pri učitavanju se čisti: uklanja se sve što nije crtež, npr. skripte ili poveznice na druge web-stranice. SVG učitan ovdje koristi se umjesto gornje slike.',
    'svg.upload' => 'Učitaj SVG',
    'svg.replace' => 'Zamijeni SVG',
    'svg.send' => 'Učitaj',
    'svg.current' => 'Ovaj SVG sada je logotip.',
    'svg.remove' => 'Ukloni SVG',
    'svg.stored' => 'SVG logotip je spremljen.',
    'svg.removed' => 'SVG logotip je uklonjen. Logotip je ponovno slika odabrana iznad, ako postoji.',
    'svg.no_file' => 'Najprije odaberite SVG datoteku.',
    'svg.cannot_write' => 'SVG nije moguće spremiti. Provjerite smije li web-poslužitelj pisati u public/m.',
    'svg.too_large' => 'Ovaj SVG veći je od :size KB. Logotip obično ima nekoliko kilobajta; izvezite ga bez ugrađenih slika ili fontova.',
    'svg.doctype' => 'Ovaj SVG navodi DOCTYPE, koji logotipu nikada ne treba i koji Boxlet ne prihvaća. Izvezite ga ponovno kao obični SVG.',
    'svg.not_svg' => 'Ova datoteka nije SVG ili je oštećena.',
    'svg.no_size' => 'Ovaj SVG nema viewBox, pa mu oblik nije poznat. Izvezite ga s viewBoxom (većina alata za dizajn to radi sama).',
];
