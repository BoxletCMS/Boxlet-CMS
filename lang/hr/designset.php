<?php

// What a design set's reader says about a file it refused or trimmed (PLAN.md D-152).
return [
    'designset.too_large' => 'Datoteka je veća od :size KB, a nijedan dizajn nije toliko velik.',
    'designset.too_deep' => 'Datoteka ima više od :depth razina ugniježđenosti, a nijedan dizajn nema toliko.',
    'designset.not_json' => 'Ova datoteka nije dizajn za Boxlet: nije JSON.',
    'designset.not_a_design' => 'Ova datoteka nije dizajn za Boxlet.',
    'designset.version' => 'Ovaj dizajn ima verziju formata :version, koju ovaj Boxlet ne može pročitati. Ažurirajte Boxlet, a zatim ga ponovno uvezite.',
    'designset.id' => 'id: dizajn treba id od malih slova, znamenki i crtica, najviše 32 znaka, koji počinje slovom ili znamenkom.',
    'designset.name' => 'name: dizajn treba naziv na barem jednom jeziku.',
    'designset.field' => ':field: :reason',
    'designset.missing' => 'nedostaje.',
    'designset.look_incomplete' => 'karakter određuje svaki izbor za zaglavlje i podnožje, a ovaj nedostaje.',
    'designset.unknown_key' => 'Izostavljeno: :key — dizajn za Boxlet to nema.',
    'designset.block_unknown' => 'Izostavljeno: :field — ova web-stranica nema takav blok.',
    'designset.layout_unknown' => 'Izostavljeno: :field — taj blok ne nudi ovaj raspored.',
    'designset.patterns_list' => 'mora biti popis uzoraka.',
    'designset.pattern_id' => 'uzorak treba vlastiti id: mala slova, znamenke i crtice.',
    'designset.pattern_blocks' => 'uzorak treba barem jedan blok koji ova web-stranica ima.',
    'designset.pattern_field' => 'Izostavljeno: :field — uzorak nosi riječi i poveznice, a ne slike, datoteke ili obrasce.',
    'designset.option_unknown' => 'Izostavljeno: :field — taj blok nema takvu mogućnost ili nema tu vrijednost.',
    'designset.option_idle' => 'Zadržano: :field, iako u rasporedu :layout ne čini ništa — djeluje samo u rasporedima koje navodi njegov blok.',
];
