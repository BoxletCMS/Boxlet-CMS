<?php

namespace App\Modules\Settings;

use App\Core\Db;
use App\Core\Request;
use App\Modules\Pages\PageLinks;
use App\Support\RichText;
use App\Support\SafeUrl;

/**
 * The owner's own WORDS in the header and footer, once per language (PLAN.md D-028, D-059):
 * the button and where it leads, the footer's text, the small print.
 *
 * Its own class because it now has two readers — the Appearance screen edits these, and the
 * preview draws them before they are saved — and because the rest of the chrome is CHOICES
 * from closed sets while this is free text that needs checking. ChromeLook is the choices;
 * this is the words.
 *
 * FORM FIELD NAMES ARE NOT SETTINGS KEYS. The field is `header_button_label_hr`; the key it
 * ends up under is SiteChrome's business alone, and chrome_test.php fails if a `chrome_`
 * literal appears outside that class. Both namespaces once shared a prefix and neither a
 * reader nor the guard could tell a posted field from a stored key.
 */
final class ChromeWords
{
    /** What the owner writes, per language; posted as <field>_<locale>. */
    private const FIELDS = [
        'button_label' => 'header_button_label',
        'button_url' => 'header_button_url',
        'button_page' => 'header_button_page',
        // The footer's columns (D-115): column 1's words keep the field the footer's text
        // always had, so a scenario or a test that typed into it still does.
        'title' => 'footer_title',
        'text' => 'footer_text',
        'col2_title' => 'footer_col2_title',
        'col2_text' => 'footer_col2_text',
        'col3_title' => 'footer_col3_title',
        'col3_text' => 'footer_col3_text',
        'small_print' => 'footer_small_print',
    ];

    /** The word fields of one footer column, by column number: [title field, text field]. */
    public const COLUMN_FIELDS = [1 => ['title', 'text'], 2 => ['col2_title', 'col2_text'], 3 => ['col3_title', 'col3_text']];

    /**
     * The posted name of one field. The view and the controller must agree on it exactly,
     * so it is composed here rather than spelled out in both.
     */
    public static function field(string $name, string $locale): string
    {
        return (self::FIELDS[$name] ?? $name) . '_' . $locale;
    }

    /**
     * What is stored, for every language the site has.
     *
     * @param list<string> $locales
     * @return array<string, array<string, string>>
     */
    public static function stored(Db $db, array $locales): array
    {
        $words = [];
        foreach ($locales as $code) {
            $header = SiteChrome::header($db, $code);
            $footer = SiteChrome::footer($db, $code);
            $entry = [
                'button_label' => $header['button']['label'],
                'button_url' => $header['button']['url'],
                'small_print' => $footer['small_print'],
            ];
            foreach (self::COLUMN_FIELDS as $n => [$title, $text]) {
                $entry[$title] = $footer['columns'][$n - 1]['title'] ?? '';
                $entry[$text] = $footer['columns'][$n - 1]['text'] ?? '';
            }
            $words[$code] = $entry;
        }

        return $words;
    }

    /**
     * What a save is trying, checked. Errors are keyed by the FIELD that carries them, so
     * the screen can put each message beside its own input.
     *
     * @param list<string> $locales
     * @return array{values: array<string, array<string, mixed>>, errors: array<string, string>}
     */
    public static function fromRequest(Request $request, array $locales): array
    {
        $values = [];
        $errors = [];
        foreach ($locales as $code) {
            // Written out rather than built in a loop: saveForLocale() takes every key, and
            // a loop only ever establishes "these keys might be present", which is a
            // promise asserted at the boundary and proved nowhere. The columns are the one
            // list, and each is written out inside it.
            $columns = [];
            foreach (self::COLUMN_FIELDS as [$title, $text]) {
                $columns[] = [
                    'title' => trim($request->input(self::field($title, $code))),
                    // Rich text since D-113, cleaned with the footer's short whitelist.
                    'text' => self::cleanText($request->input(self::field($text, $code))),
                ];
            }
            $entry = [
                'button_label' => trim($request->input(self::field('button_label', $code))),
                'button_url' => SafeUrl::normalize($request->input(self::field('button_url', $code))),
                'columns' => $columns,
                'small_print' => trim($request->input(self::field('small_print', $code))),
            ];
            // And the same words under their field names, which is how the screen reads
            // them back when a post is answered with the screen — a character loaded, a
            // refusal — rather than a redirect. Both shapes, one source.
            foreach (self::COLUMN_FIELDS as $n => [$title, $text]) {
                $entry[$title] = $columns[$n - 1]['title'];
                $entry[$text] = $columns[$n - 1]['text'];
            }
            // A chosen page wins over a typed address, as in a block's link field (D-034);
            // the address input is hidden while a page is chosen.
            $page = trim($request->input(self::field('button_page', $code)));
            if (preg_match('~^[1-9][0-9]{0,9}$~', $page) === 1) {
                $entry['button_url'] = PageLinks::to((int) $page);
            }
            // The same guard the menu builder uses for an item's address: an address we
            // would not follow is refused here rather than written into every page's header.
            if ($entry['button_url'] !== '' && !SafeUrl::isLink($entry['button_url'])) {
                $errors[self::field('button_url', $code)] = t('chrome.button_url_refused');
            }
            $values[$code] = $entry;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * The footer's text as it is stored (D-113): the whitelist's HTML, a line or two with a
     * link in it. A bare email or phone number in a link becomes the link it was meant to
     * be, as everywhere (D-039).
     */
    public static function cleanText(string $raw): string
    {
        return RichText::sanitize($raw, RichText::INLINE);
    }

    /**
     * The footer's text as HTML, whatever version stored it. Text stored before D-113 is
     * plain, with line breaks; handed to an HTML editor — or to the block machinery, which
     * cleans a rich text field as HTML — as it is, its breaks would collapse into one
     * paragraph. So a plain text becomes one paragraph with its breaks kept, which is exactly
     * what the page drew for it, and both the editor and the page are handed that.
     */
    public static function asHtml(string $stored): string
    {
        if ($stored === '' || self::isHtml($stored)) {
            return $stored;
        }

        return '<p>' . nl2br(htmlspecialchars($stored, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>';
    }

    /**
     * Whether a stored footer text is HTML (saved since D-113) or plain (saved before). One
     * rule, read by the template too, so what the page draws and what the editor is handed
     * can never disagree about the same string.
     */
    public static function isHtml(string $stored): bool
    {
        return str_contains($stored, '<');
    }

    /**
     * @param array<string, array<string, mixed>> $values each language's entry, as fromRequest() returns it
     */
    public static function save(Db $db, array $values): void
    {
        foreach ($values as $code => $entry) {
            SiteChrome::saveForLocale($db, $code, $entry);
        }
    }
}
