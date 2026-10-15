<?php

namespace App\Modules\Ai;

use App\Core\Db;
use App\Modules\Settings\ChromeWords;
use App\Modules\Settings\SiteChrome;
use Closure;

/**
 * A LANGUAGE'S MENUS AND HEADER AND FOOTER WORDS, TRANSLATED (PLAN.md D-262, the owner): what
 * a translated page's language still says in the original's words, or does not say at all.
 *
 * MENU ITEMS: only those Boxlet copied (MenuCopy, source_item_id) and nobody has renamed since:
 * an item of a page whose original has a label of its own and the copy none (it shows the page's
 * title until then), and an address whose label is still the original's word for word. An item
 * the owner named is theirs. Structure and where items lead are MenuCopy's, never a model's.
 *
 * HEADER AND FOOTER WORDS (ChromeWords): each the original language has and this one leaves
 * empty, which draws nothing in this language. A word the owner wrote here is never replaced.
 * The button's address comes with its label when this language has none: a page link is the
 * same page in every language (page:{n}, D-034).
 *
 * Live at once, as Navigation's saves are: there is no draft of a menu.
 */
final class AiNavigation
{
    /** The header and footer words, by field: whether each is rich text. */
    private const WORDS = [
        'button_label' => false, 'small_print' => false,
        'title' => false, 'text' => true, 'col2_title' => false, 'col2_text' => true, 'col3_title' => false, 'col3_text' => true,
    ];

    /**
     * What $to still lacks, by a key of this class's: menu:{item id} or words:{field}.
     *
     * @return array<string, array{text: string, html: bool}>
     */
    public static function missing(Db $db, string $from, string $to): array
    {
        if ($from === $to) {
            return [];
        }
        $texts = [];
        foreach ($db->all(
            'SELECT c.id, c.label, c.page_id, s.label AS source_label FROM menu_items c
             JOIN menus m ON m.id = c.menu_id JOIN menu_items s ON s.id = c.source_item_id
             WHERE m.locale = ? AND c.source_item_id IS NOT NULL ORDER BY c.id',
            [$to],
        ) as $item) {
            $original = trim((string) ($item['source_label'] ?? ''));
            $own = $item['label'] === null ? '' : (string) $item['label'];
            $untouched = $item['page_id'] !== null ? $own === '' : $own === $original;
            if ($original !== '' && $untouched) {
                $texts['menu:' . (int) $item['id']] = ['text' => $original, 'html' => false];
            }
        }
        $words = ChromeWords::stored($db, [$from, $to]);
        foreach (self::WORDS as $field => $html) {
            $original = (string) ($words[$from][$field] ?? '');
            if (trim(strip_tags($original)) !== '' && trim(strip_tags((string) ($words[$to][$field] ?? ''))) === '') {
                $texts['words:' . $field] = ['text' => $original, 'html' => $html];
            }
        }

        return $texts;
    }

    /**
     * Translates what is missing and writes it. Returns how many kept no translation.
     *
     * @param array{keep: string, voice: string} $words
     * @param Closure(string, string): array{text: string, in: int, out: int} $ask
     */
    public static function run(Db $db, string $from, string $to, string $fromName, string $toName, array $words, Closure $ask): int
    {
        $texts = self::missing($db, $from, $to);
        if ($texts === []) {
            return 0;
        }
        $result = AiTranslator::translate($texts, $fromName, $toName, $words, $ask);
        AiSettings::addUsage($db, $result['in'], $result['out']);

        $stored = ChromeWords::stored($db, [$from, $to]);
        $entry = $stored[$to] ?? [];
        $wrote = false;
        foreach ($result['done'] as $key => $text) {
            [$kind, $name] = explode(':', $key, 2);
            if ($kind === 'menu') {
                $db->query('UPDATE menu_items SET label = ?, updated_at = ? WHERE id = ?', [mb_substr($text, 0, 255), gmdate('Y-m-d H:i:s'), (int) $name]);
                continue;
            }
            $entry[$name] = self::WORDS[$name] ? ChromeWords::cleanText($text) : mb_substr($text, 0, 255);
            if ($name === 'button_label' && (string) ($entry['button_url'] ?? '') === '') {
                $entry['button_url'] = (string) ($stored[$from]['button_url'] ?? '');
            }
            $wrote = true;
        }
        if ($wrote) {
            $columns = [];
            foreach (ChromeWords::COLUMN_FIELDS as [$title, $text]) {
                $columns[] = ['title' => (string) ($entry[$title] ?? ''), 'text' => (string) ($entry[$text] ?? '')];
            }
            SiteChrome::saveForLocale($db, $to, [
                'button_label' => (string) ($entry['button_label'] ?? ''),
                'button_url' => (string) ($entry['button_url'] ?? ''),
                'columns' => $columns,
                'small_print' => (string) ($entry['small_print'] ?? ''),
            ]);
        }

        return count($result['failed']);
    }
}
