<?php

namespace App\Modules\Pages;

use App\Support\RichText;
use App\Support\SafeUrl;

/**
 * ONE VALUE, CLEANED FOR ITS FIELD (PLAN.md D-173): what a form's field sent, or what a
 * document holds, made what the store may keep — rich text sanitised to its field's list, a
 * line one printable line, a link a link, a choice one of its options, a repeater item by
 * item. Out of BlockForm, which is the form — keys, order, the editor's no-script actions —
 * when documents began to need the same checks a form's fields pass.
 */
final class BlockValues
{
    /**
     * @param array<string, mixed> $field
     * @return array{0: mixed, 1: string|null} the cleaned value and an error, if any
     */
    public static function field(array $field, mixed $raw): array
    {
        $required = $field['required'] === true;

        /*
         * A REPEATER IS THE SAME QUESTION, ONCE PER ITEM (PLAN.md O-11).
         *
         * Each item's fields go through this very method, so a media field inside an item
         * is validated as a media field and a richtext field is sanitised per item — no
         * special case, and nothing here has to know which types exist.
         *
         * Over the maximum is REFUSED rather than trimmed, unlike on render: here somebody
         * typed those items, and silently dropping the last one is how an owner loses work
         * without being told. Blocks::normalize() trims instead, because a page must still
         * draw when a definition's maximum shrinks under it.
         */
        if ($field['type'] === 'repeater') {
            $rows = is_array($raw) ? array_values($raw) : [];
            $items = [];
            $itemError = null;
            foreach ($rows as $row) {
                if (($row['_delete'] ?? '') === '1') {
                    continue;
                }
                $item = [];
                foreach ($field['fields'] as $itemName => $itemField) {
                    [$value, $error] = self::field($itemField, is_array($row) ? ($row[$itemName] ?? null) : null);
                    $item[$itemName] = $value;
                    // The first thing wrong, named once: a message per item per field would
                    // bury the block's own errors under a list nobody reads.
                    $itemError ??= $error;
                }
                $items[] = $item;
            }

            if (count($items) > $field['max']) {
                return [array_slice($items, 0, $field['max']), t('pages.field.repeater_max', ['max' => $field['max']])];
            }
            if ($items === [] && $required) {
                return [[], t('pages.field.required')];
            }

            return [$items, $itemError];
        }

        switch ($field['type']) {
            case 'link':
                $label = is_array($raw) ? self::line($raw['label'] ?? null) : '';
                // A bare email or phone number becomes the link it was meant to be (D-039).
                $url = SafeUrl::normalize(is_array($raw) ? self::line($raw['url'] ?? null) : '');
                // A chosen page wins over a typed address (PLAN.md D-034): the address input
                // is hidden while a page is chosen, so whatever it still holds is stale.
                $page = is_array($raw) ? self::line($raw['page'] ?? null) : '';
                if (preg_match('~^[1-9][0-9]{0,9}$~', $page) === 1) {
                    $url = PageLinks::to((int) $page);
                }
                $value = ['label' => $label, 'url' => $url];
                if ($label === '' && $url === '') {
                    return [$value, $required ? t('pages.field.required') : null];
                }
                if ($url === '') {
                    return [$value, t('pages.field.link_url_missing')];
                }
                if (!SafeUrl::isLink($url)) {
                    return [$value, t('pages.field.link_url')];
                }
                // A page supplies its own title when the text is left empty; an address
                // has nothing to say about itself.
                if ($label === '' && PageLinks::reference($url) === null) {
                    return [$value, t('pages.field.link_label')];
                }

                return [$value, null];

            case 'media':
            case 'file':
            case 'form':
                $text = self::line($raw);
                if ($text === '') {
                    return [null, $required ? t('pages.field.required') : null];
                }

                return ctype_digit($text) && (int) $text > 0 ? [(int) $text, null] : [null, t('pages.field.media')];

            case 'select':
                $options = $field['options'];
                if (is_string($raw) && in_array($raw, $options, true)) {
                    return [$raw, null];
                }
                /* NOT SENT IS NOT WRONG (PLAN.md D-118). A form that has never heard of a
                   choice — an editor opened before the block gained it, a save written
                   before it existed — says nothing about it, and the choice takes its first
                   option, as a stored block without it does (Blocks::normalize). A value that
                   IS sent and is not one of the options is still refused. Adding two choices
                   to the hero failed every such save with "Choose one of the options" against
                   fields the author could not see. */
                if ($raw === null) {
                    return [$options[0], null];
                }

                return [$options[0], t('pages.field.select')];

            case 'richtext':
                // What this field allows, from its definition (D-166): the same list its toolbar offers.
                $html = RichText::sanitize(is_string($raw) ? $raw : '', RichText::allowedFor($field['allow'] ?? RichText::FEATURES));
                $empty = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '';

                return [$empty ? '' : $html, $empty && $required ? t('pages.field.required') : null];

            case 'textarea':
                $text = is_string($raw) ? trim(self::printable(str_replace("\r\n", "\n", $raw))) : '';

                return [$text, $text === '' && $required ? t('pages.field.required') : null];

            default: // text
                $text = self::line($raw);

                return [$text, $text === '' && $required ? t('pages.field.required') : null];
        }
    }

    /**
     * A single-line string: valid UTF-8, no control characters, no line breaks.
     */
    private static function line(mixed $raw): string
    {
        return is_string($raw) ? trim(self::printable(str_replace(["\r", "\n"], ' ', $raw))) : '';
    }

    private static function printable(string $text): string
    {
        return (string) preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~', '', mb_scrub($text, 'UTF-8'));
    }

    /**
     * A stored value in the shape a form sends it: numbers as text, a link's page reference
     * as the page a link field chooses.
     *
     * @param array<string, mixed> $field
     */
    public static function asSent(array $field, mixed $value): mixed
    {
        if ($field['type'] === 'repeater') {
            return is_array($value) ? array_map(static function (mixed $item) use ($field): mixed {
                if (!is_array($item)) {
                    return $item;
                }
                foreach ($field['fields'] as $name => $declared) {
                    $item[$name] = self::asSent($declared, $item[$name] ?? null);
                }

                return $item;
            }, array_values($value)) : [];
        }
        if ($field['type'] === 'link' && is_array($value)) {
            $url = is_string($value['url'] ?? null) ? $value['url'] : '';

            return preg_match(SafeUrl::PAGE_REFERENCE, $url, $page) === 1
                ? ['label' => $value['label'] ?? '', 'url' => '', 'page' => $page[1]]
                : ['label' => $value['label'] ?? '', 'url' => $url];
        }

        return is_int($value) ? (string) $value : $value;
    }

    /**
     * A link whose address is no link keeps its words and loses the address, item by item.
     *
     * @param array<string, mixed> $field
     */
    public static function linked(array $field, mixed $value): mixed
    {
        if ($field['type'] === 'repeater' && is_array($value)) {
            foreach ($value as $at => $item) {
                foreach ($field['fields'] as $name => $declared) {
                    $value[$at][$name] = self::linked($declared, $item[$name] ?? null);
                }
            }

            return $value;
        }
        if ($field['type'] === 'link' && is_array($value) && is_string($value['url'] ?? null) && $value['url'] !== '' && !SafeUrl::isLink($value['url'])) {
            $value['url'] = '';
        }

        return $value;
    }
}
