<?php

/*
 * The demo's forms (PLAN.md D-213), by the key a page names them with (`demo:form:{key}`):
 * the newsletter, a workshop sign-up, a hire enquiry and the contact form. Each is made in
 * the language of the pages that show it, its labels in that language, because visitors
 * read them. The contact form is Boxlet's own new form, as an owner gets one.
 *
 * @return array<string, array{name: string, fields: list<array{key: string, type: string, label: string, required: bool, options: list<string>}>|null}>
 */
return static function (string $lang): array {
    $t = static fn (string $hr, string $en): string => $lang === 'hr' ? $hr : $en;
    $field = static fn (string $key, string $type, string $hr, string $en, bool $required = true, array $options = []): array => [
        'key' => $key, 'type' => $type, 'label' => $t($hr, $en), 'required' => $required, 'options' => $options,
    ];

    return [
        'contact' => ['name' => $t('Kontakt', 'Contact'), 'fields' => null],
        'newsletter' => ['name' => $t('Novosti', 'Newsletter'), 'fields' => [
            $field('email', 'email', 'E-mail adresa', 'Email address'),
        ]],
        'workshop' => ['name' => $t('Prijava na radionicu', 'Workshop sign-up'), 'fields' => [
            $field('name', 'text', 'Ime i prezime', 'Name'),
            $field('email', 'email', 'E-mail', 'Email'),
            $field('workshop', 'select', 'Radionica', 'Workshop', true, $lang === 'hr'
                ? ['Linorez', 'Sitotisak', 'Keramika', 'Šivanje', 'Papir i uvez', 'Tipografija']
                : ['Linocut', 'Screen printing', 'Ceramics', 'Sewing', 'Paper & binding', 'Letterpress']),
            $field('message', 'textarea', 'Poruka', 'Message', false),
        ]],
        'hire' => ['name' => $t('Upit za najam', 'Hire enquiry'), 'fields' => [
            $field('name', 'text', 'Ime i prezime', 'Name'),
            $field('email', 'email', 'E-mail', 'Email'),
            $field('phone', 'tel', 'Telefon', 'Phone', false),
            $field('room', 'select', 'Prostor', 'Room', true, $lang === 'hr'
                ? ['Velika dvorana', 'Studio', 'Seminarska dvorana', 'Kino dvorana']
                : ['Main hall', 'Studio', 'Seminar room', 'Screening room']),
            $field('date', 'text', 'Datum i broj ljudi', 'Date and number of people'),
            $field('message', 'textarea', 'Što planirate', 'What you are planning'),
        ]],
    ];
};
