<?php

// The suggested privacy-policy text for visit statistics (PLAN.md D-051), in Croatian.
// See lang/en/stats-privacy.php.
return [
    'privacy.language' => 'Hrvatski',
    'privacy.heading' => 'Statistika posjeta',
    'privacy.what' => 'Posjete ovim web stranicama brojimo na vlastitom poslužitelju kako bismo znali koje se stranice čitaju i kako nas posjetitelji pronalaze. Ne koristimo kolačiće, skripte za praćenje ni vanjske usluge za analitiku.',
    'privacy.visitor' => 'Kad otvorite stranicu, naš poslužitelj čita vašu IP adresu i oznaku vašeg preglednika (User-Agent) samo kako bi prepoznao ponovljene posjete istoga dana. Od njih izračunava jednosmjerni kod pomoću ključa koji se mijenja svaki dan. Sama adresa nikad se ne sprema, a kod se briše na kraju dana, pa vas nije moguće prepoznati iz dana u dan.',
    'privacy.country' => 'U trenutku posjeta vašu IP adresu koristimo i kako bismo u bazi na našem poslužitelju pronašli državu iz koje dolazite (IP geolokacija: DB-IP). Sprema se samo država.',
    // Jedan od ova tri stoji umjesto druga dva, prema postavci (D-055).
    'privacy.region' => 'U trenutku posjeta vašu IP adresu koristimo i kako bismo u bazi na našem poslužitelju pronašli državu i regiju iz koje dolazite (IP geolokacija: DB-IP). Spremaju se samo država i regija, ništa uže od toga.',
    'privacy.city' => 'U trenutku posjeta vašu IP adresu koristimo i kako bismo u bazi na našem poslužitelju pronašli državu, regiju i grad iz kojeg dolazite (IP geolokacija: DB-IP). Lokacija je približna: na mobilnoj mreži to je najčešće grad operatera. Sprema se kao dnevni zbroj za samo to mjesto, nikada zajedno sa stranicama koje ste čitali.',
    'privacy.city_floor' => 'Mjesta s vrlo malo posjetitelja broje se zajedno kako zbroj ne bi mogao upućivati na jednu osobu.',
    'privacy.kept' => 'Čuvamo samo dnevne zbrojeve: pregledane stranice, web stranicu s koje ste došli (samo njezinu domenu), državu te vrstu uređaja, preglednika i operacijskog sustava. Ništa od toga vas ne identificira. Zbrojevi se automatski brišu nakon :period.',
    'privacy.dnt' => 'Ako vaš preglednik šalje signal Do Not Track ili Global Privacy Control, vaš se posjet ne broji.',
    'privacy.basis' => 'Pravna osnova: naš legitimni interes da razumijemo kako se naše web stranice koriste (članak 6. stavak 1. točka (f) GDPR-a).',
    'privacy.period.6' => '6 mjeseci',
    'privacy.period.12' => '12 mjeseci',
    'privacy.period.24' => '24 mjeseca',
    'privacy.period.36' => '36 mjeseci',
];
