<?php

// The suggested privacy-policy text for visit statistics (PLAN.md D-051), in English. The
// owner copies it from Settings into their own privacy page; Boxlet never shows it to a
// visitor. One file per language, lang/{code}/stats-privacy.php, so another language is a
// new file. Paragraphs that depend on a setting are separate keys, left out when they do
// not apply.
return [
    'privacy.language' => 'English',
    'privacy.heading' => 'Visitor statistics',
    'privacy.what' => 'We count visits to this website on our own server, to learn which pages are read and how visitors find us. We use no cookies, no tracking scripts and no third-party analytics service.',
    'privacy.visitor' => 'When you open a page, our server reads your IP address and your browser\'s identification (User-Agent) only to recognise repeat visits on the same day. From them it makes a one-way code with a key that is replaced every day. The address itself is never stored, and the code is deleted at the end of the day, so you cannot be recognised from one day to the next.',
    'privacy.country' => 'At the moment of your visit, your IP address is also used to look up your country in a database kept on our server (IP geolocation by DB-IP). Only the country is kept.',
    // One of these three stands in for the other two, by the setting (D-055). Each says
    // what is looked up AND what is kept, because the paragraph after it lists the country
    // alone and would otherwise understate it.
    'privacy.region' => 'At the moment of your visit, your IP address is also used to look up your country and region in a database kept on our server (IP geolocation by DB-IP). The country and the region are kept, and nothing narrower than that.',
    /* THE LAST CLAUSE IS ITS OWN SENTENCE SINCE D-109, and it is only shown while it is
       true. The owner may lower the floor under the cities to one, and a text telling
       visitors that "a total cannot point at one person" while every city is named would be
       the worst kind of wrong — which is the rule PrivacyText was written around. */
    'privacy.city' => 'At the moment of your visit, your IP address is also used to look up your country, region and city in a database kept on our server (IP geolocation by DB-IP). The location is approximate: on a mobile network it is usually the operator\'s city. It is kept as a daily total for the place alone, never together with the pages that were read.',
    'privacy.city_floor' => 'Places with very few visitors are counted together, so that a total cannot point at one person.',
    'privacy.kept' => 'We keep only daily totals: the pages viewed, the website you came from (its domain only), the country, and the type of device, browser and operating system. None of this identifies you. The totals are deleted automatically after :period.',
    'privacy.dnt' => 'If your browser sends a Do Not Track or Global Privacy Control signal, your visit is not counted.',
    'privacy.basis' => 'Legal basis: our legitimate interest in understanding how our website is used (Article 6(1)(f) GDPR).',
    'privacy.period.6' => '6 months',
    'privacy.period.12' => '12 months',
    'privacy.period.24' => '24 months',
    'privacy.period.36' => '36 months',
];
