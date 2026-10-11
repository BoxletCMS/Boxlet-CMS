<?php

/*
 * THE DEMO SITE (PLAN.md D-213): The Printworks, a community arts centre in a former printing
 * works. Ten pages and three beneath them, each in a file of its own under pages/, and the
 * showroom (blocks.php), unlisted, that shows every block in every layout.
 *
 * Every page is written in both of the demo's languages: a site whose first language is
 * Croatian gets the Croatian words, any other the English (the owner, D-213). A function of
 * the language rather than a list, so one definition makes both — they can only differ in
 * words.
 *
 * Each page is SECTIONS, as the builder makes them: a section's own style (only the keys the
 * page sets; every other is the character's, D-165), its layout, how it stacks on a phone
 * where it says, and its blocks with the column each stands in. A block is
 * [type, content, layout, options, column].
 *
 * Markers the seed replaces on the way in: `demo:{key}` a page by its key (a reference, D-034),
 * `demo:form:{key}` one of forms.php's forms, `demo-picture:{folder/name}` a picture from
 * demo_images/ (in a block's content or a section's background), `demo-file:{name}` one of
 * demo_images/files/'s PDFs in the page's language.
 *
 * An event's day is counted from the day the demo is installed ($d, $on), so a new demo never
 * opens on a past programme.
 *
 * @return list<array{key: string, parent?: string, slug: string, title: string, description: string, menu: string, sections: list<array{style: array<string, string>, layout: string, stack?: string, blocks: list<array{string, array<string, mixed>, string, array<string, string>, int}>}>}>
 */
return static function (string $lang): array {
    $t = static fn (string $hr, string $en): string => $lang === 'hr' ? $hr : $en;
    $p = static fn (string $hr, string $en): string => '<p>' . $t($hr, $en) . '</p>';
    $months = $lang === 'hr'
        ? ['siječnja', 'veljače', 'ožujka', 'travnja', 'svibnja', 'lipnja', 'srpnja', 'kolovoza', 'rujna', 'listopada', 'studenoga', 'prosinca']
        : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $weekdays = $lang === 'hr'
        ? ['nedjelja', 'ponedjeljak', 'utorak', 'srijeda', 'četvrtak', 'petak', 'subota']
        : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $day = static fn (int $days): int => (new DateTimeImmutable('today'))->modify(($days >= 0 ? '+' : '') . $days . ' days')->getTimestamp();
    // 12 October / 12. listopada
    $on = static function (int $days) use ($day, $months, $lang): string {
        $at = $day($days);

        return $lang === 'hr'
            ? date('j', $at) . '. ' . $months[(int) date('n', $at) - 1]
            : date('j', $at) . ' ' . $months[(int) date('n', $at) - 1];
    };
    // Thursday 12 October, 19:00 / četvrtak, 12. listopada, 19:00
    $d = static function (int $days, string $time) use ($day, $on, $weekdays, $lang): string {
        $weekday = $weekdays[(int) date('w', $day($days))];

        return ($lang === 'hr' ? $weekday . ', ' : $weekday . ' ') . $on($days) . ($time !== '' ? ', ' . $time : '');
    };

    $pages = [];
    foreach (['home', 'program', 'exhibitions', 'floating-world', 'workshops', 'linocut', 'cafe', 'membership', 'hire', 'about', 'journal', 'press-hall', 'visit'] as $key) {
        $pages[] = (require __DIR__ . '/pages/' . $key . '.php')($t, $p, $d, $on);
    }
    $pages[] = (require __DIR__ . '/blocks.php')($t, $p);

    return $pages;
};
