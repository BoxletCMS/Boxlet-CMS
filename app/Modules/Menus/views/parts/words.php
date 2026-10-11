<?php

/**
 * The owner's words in the header and footer, per language (PLAN.md D-111, D-180, D-182), and
 * the way Navigation explains a field. Required by navigation.php, in its scope.
 *
 * @var array<string, array<string, mixed>> $words
 * @var array<int, array<string, mixed>> $locales
 * @var string $shownLocale the site's main language, the one the screen opens on
 */

/** A stored word of the owner's, for one language; '' when there is none. */
$word = static fn (string $code, string $field): string => is_string($words[$code][$field] ?? null)
    ? $words[$code][$field]
    : '';

/**
 * ONE LANGUAGE'S WORDS (D-182): the language is chosen once, at the top of the screen, and
 * each card shows that language's words, with no card inside a card. Without a script every
 * language stands in turn, under its name.
 */
$wordsPanel = static function (string $code, string $legend, string $inside) use ($locales): string {
    if (count($locales) < 2) {
        return '<div class="words-for">' . $inside . '</div>';
    }
    $label = '';
    foreach ($locales as $locale) {
        if ((string) $locale['code'] === $code) {
            $label = (string) $locale['label'];
        }
    }

    return '<div class="words-for" data-locale="' . e($code) . '"><p class="words-language">' . e($legend . ': ' . $label) . '</p>' . $inside . '</div>';
};

/**
 * A FIELD'S HINT IN ONE SENTENCE (D-182): the first sentence under the field, and the rest,
 * if any, behind a (?) that shows it on hover and on focus and is read with the field. The (?)
 * is held to the sentence's last word, so it never stands on a line of its own (D-183).
 */
$hint = static function (string $id, string $text): string {
    $first = $text;
    $rest = '';
    if (preg_match('~^(.+?[.!?])\s+(\S.*)$~su', $text, $parts) === 1) {
        [$first, $rest] = [$parts[1], $parts[2]];
    }
    if ($rest === '') {
        return '<span class="hint" id="' . e($id) . '">' . e($first) . '</span>';
    }
    $head = '';
    $last = $first;
    if (preg_match('~^(.*\s)(\S+)$~su', $first, $words) === 1) {
        [$head, $last] = [$words[1], $words[2]];
    }

    return '<span class="hint" id="' . e($id) . '">' . e($head)
        . '<span class="hint-tail">' . e($last) . ' <span class="help-tip"><button type="button" class="help-tip-button" aria-describedby="' . e($id) . '-more" aria-label="' . e(t('navigation.more')) . '">?</button>'
        . '<span class="help-tip-text" role="tooltip" id="' . e($id) . '-more">' . e($rest) . '</span></span></span></span>';
};
