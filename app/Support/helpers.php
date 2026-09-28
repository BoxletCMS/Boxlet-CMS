<?php

/**
 * Escape a value for HTML text and attribute context.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Read a variable from .env or the real environment. Returns the raw string: nothing
 * is cast, so a password spelled "true" stays a string. Empty values count as missing.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

    return is_string($value) && $value !== '' ? $value : $default;
}

/** The admin's own language. Slice 6 adds locales for the SITE; the admin has one. */
const ADMIN_LANG = 'en';

/**
 * Boxlet's own page, for the one line a site may carry in its footer about what made it
 * (PLAN.md O-20).
 *
 * In code rather than in config/: every install credits the same project, a credit a site
 * could point anywhere is not a credit, and config/ is the part of a Boxlet an owner may
 * have edited — so it is the part an update cannot safely replace.
 */
const BOXLET_SITE = 'https://boxlet.org';

/**
 * Admin UI string from lang/{locale}/ with :name placeholders replaced. A missing key
 * returns the key itself, so it shows up rather than rendering blank.
 *
 * @param array<string, string|int> $replace
 */
function t(string $key, array $replace = []): string
{
    static $strings = null;
    if ($strings === null) {
        // Every file in the admin locale's directory, not a list of names: the strings
        // were split by concern when one file passed the 300-line rule, and a loader that
        // names its files means editing this function every time another concern earns
        // one. A key defined in two files is a test failure (tests/lang_test.php), not a
        // silent win for whichever loads first.
        //
        // Nested by locale, so that Slice 6 adding lang/hr/ cannot merge Croatian into
        // English by sitting beside it. ADMIN_LANG is the one locale there is today; it
        // is a constant rather than a setting, because switching it is a feature nobody
        // has asked for yet.
        $strings = [];
        foreach (glob(dirname(__DIR__, 2) . '/lang/' . ADMIN_LANG . '/*.php') ?: [] as $file) {
            $part = require $file;
            if (is_array($part)) {
                $strings += $part;
            }
        }
    }
    $text = is_array($strings) && is_string($strings[$key] ?? null) ? $strings[$key] : $key;
    foreach ($replace as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }

    return $text;
}

/**
 * An icon from the admin's sprite (PLAN.md D-037), drawn in the current text colour.
 *
 * DECORATION ONLY: it is hidden from assistive technology, so whatever it sits in must say
 * what it does in words — visible text beside it, or a visually-hidden label and a title
 * for a control that shows the icon alone.
 */
function icon(string $name): string
{
    $href = \App\Support\Url::versioned('assets/vendor/icons.svg') . '#i-' . $name;

    return '<svg class="icon" aria-hidden="true" focusable="false"><use href="' . e($href) . '"></use></svg>';
}

/**
 * The description under a field, when there is one (PLAN.md D-038): the escaped hint, or ''
 * for a key lang/ does not define. Descriptions live in lang/en/hints.php as hint.* keys, so
 * a field without one simply shows none rather than its key.
 */
function field_hint(string $key, string $id = ''): string
{
    $text = t($key);
    if ($text === $key) {
        return '';
    }

    return '<span class="hint"' . ($id !== '' ? ' id="' . e($id) . '"' : '') . '>' . e($text) . '</span>';
}

/**
 * A CLOSED SET AS A ROW OF BUTTONS (PLAN.md D-065, D-107).
 *
 * Written for the Appearance screen and made a helper when the page editor's Section panel
 * became the second caller: the same question — one of these few — asked about a band
 * instead of about the site. The markup is here once rather than in two views that drift,
 * and admin.css carries the look for the same reason.
 *
 * RADIO INPUTS, not buttons with a hidden field: they submit without a script, the browser
 * gives arrow-key movement inside the group for free, and a screen reader already knows what
 * a radio group is. Which is also why this replaced a <select> in the Section panel and lost
 * nothing: the name and the values posted are the same, so the save never learns of it.
 *
 * @param string $name the form name every radio in the group carries
 * @param array<array-key, string> $labels value => what it is called
 * @param string $labelledBy the id of the element naming this group, for aria-labelledby
 * @param string $idPrefix each radio's id is this plus its value, so a <label for> can find it
 */
function segmented_group(string $name, array $labels, string $current, string $labelledBy, string $idPrefix): string
{
    $html = '<div class="segmented-choice" role="radiogroup" aria-labelledby="' . e($labelledBy) . '">';
    foreach ($labels as $value => $label) {
        $value = (string) $value;
        $html .= '<label class="segment"><input type="radio" id="' . e($idPrefix . ($value === '' ? 'none' : $value)) . '"'
            . ' name="' . e($name) . '" value="' . e($value) . '"' . ($value === $current ? ' checked' : '') . '>'
            . '<span>' . e($label) . '</span></label>';
    }

    return $html . '</div>';
}

/**
 * The shortest name a value has: `style.surface.short.tinted` when one is written, else its
 * ordinary label. A segment is a button a few characters wide, and "Image (shown as contrast
 * until a picture is chosen)" is a sentence — but it is the right sentence in a <select> and
 * in a hint, so the long one is not replaced, only passed over where there is no room.
 */
function short_label(string $prefix, string $value): string
{
    $short = t($prefix . '.short.' . $value);

    return $short === $prefix . '.short.' . $value ? t($prefix . '.' . $value) : $short;
}

/**
 * What the site itself says to a visitor (PLAN.md O-19, D-044): the few words Boxlet puts on
 * a page that are not the owner's — "page not found", the name of the menu and of the
 * language switcher. Not t(), which is the admin's own language.
 *
 * In the page's language where lang/{code}/site.php has it, else the site's main language, else
 * English: a language added from the admin that Boxlet has no words for falls back the way
 * a translation's missing alt text does (D-043), and English is the last resort because it
 * is the one set that is always complete.
 */
function site_t(string $key, string $locale): string
{
    static $loaded = [];
    foreach ([$locale, \App\Support\Url::primaryLocale(), 'en'] as $code) {
        if (preg_match('~^[a-z]{2,3}$~', $code) !== 1) {
            continue;
        }
        if (!array_key_exists($code, $loaded)) {
            $file = dirname(__DIR__, 2) . '/lang/' . $code . '/site.php';
            $strings = is_file($file) ? require $file : [];
            $loaded[$code] = is_array($strings) ? $strings : [];
        }
        if (is_string($loaded[$code][$key] ?? null)) {
            return $loaded[$code][$key];
        }
    }

    return $key;
}
