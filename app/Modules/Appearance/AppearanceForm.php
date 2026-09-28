<?php

namespace App\Modules\Appearance;

use App\Core\Request;
use App\Modules\Design\Palette;
use App\Modules\Design\Tokens;
use App\Modules\Settings\ChromeLook;
use App\Modules\Settings\ChromeWords;
use App\Modules\Settings\SiteChrome;

/**
 * What one Appearance screen sends, and what its preview reads back (PLAN.md D-059).
 *
 * TWO SCREENS BECAME ONE, so two forms became one form: the design's ten decisions, the
 * chrome's seven look choices, which menu the header shows, and the owner's words in each
 * language. They are saved together, refused together, and previewed together — and both
 * the screen and the preview have to agree, field by field, on what was sent. That agreement
 * lives here rather than in each of them.
 */
final class AppearanceForm
{
    /** Which menu the header shows, posted by name. A field name is not a settings key. */
    public const MENU = 'header_menu';

    /**
     * Each footer column's menu (D-115), posted by column number: '' for none, `header`
     * for the header's, else a name. Field names, not settings keys.
     *
     * @return list<string>
     */
    public static function footerMenuFields(): array
    {
        $fields = [];
        foreach (range(1, SiteChrome::FOOTER_COLUMNS) as $n) {
            $fields[] = 'footer_menu_' . $n;
        }

        return $fields;
    }

    /**
     * Everything a save is trying, checked. Design errors are keyed by the decision at
     * fault, word errors by the field that carries them; one screen shows both.
     *
     * @param list<string> $locales
     * @return array{decisions: array<string, string>, look: array<string, string>, menu: string, footer_menus: array<int, string>, words: array<string, array<string, mixed>>, errors: array<string, string>}
     */
    public static function read(Request $request, array $locales): array
    {
        $design = Tokens::validate(self::decisions($request->body));
        $words = ChromeWords::fromRequest($request, $locales);
        // Every choice, not only the ones the request named: a save writes all seven, and a
        // choice the form did not send is one the owner cleared.
        $look = [];
        foreach (array_keys(ChromeLook::OPTIONS) as $choice) {
            $look[$choice] = trim($request->input(ChromeLook::field($choice)));
        }

        return [
            'decisions' => $design['decisions'],
            'look' => $look,
            'menu' => trim($request->input(self::MENU)),
            'footer_menus' => self::footerMenusFrom($request->body),
            'words' => $words['values'],
            'errors' => $design['errors'] + $words['errors'],
        ];
    }

    /**
     * The query the preview, the stylesheet and the check endpoints are called with: the
     * whole screen, so the picture is of what is on it rather than of what is stored.
     *
     * The words are only the PREVIEWED LANGUAGE's. The preview draws one page in one
     * language; the other languages' words are on the screen but not in the picture.
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, menu: string, footer_menus?: array<int, string>, words: array<string, array<string, mixed>>} $state
     * @return array<string, string>
     */
    public static function query(array $state, string $locale, string $character = ''): array
    {
        $query = $state['decisions'] + ['use_secondary' => $state['decisions']['secondary'] !== '' ? '1' : '0'];
        // Each hand-set colour needs its switch in the query too, or the preview reads a
        // colour the form only carries as a default and draws something nobody chose.
        foreach (Palette::BY_HAND as $role) {
            $query['color_' . $role . '_on'] = ($state['decisions']['color_' . $role] ?? '') !== '' ? '1' : '0';
        }
        // And the three places that may take a colour of their own, for the same reason
        // (D-076): the input always carries a colour, so only the switch says it is meant.
        foreach (Tokens::OWN_COLOURS as $field) {
            $query[$field . '_on'] = ($state['decisions'][$field] ?? '') !== '' ? '1' : '0';
        }
        foreach ($state['look'] as $choice => $value) {
            $query[ChromeLook::field($choice)] = $value;
        }
        $query[self::MENU] = $state['menu'];
        foreach (self::footerMenuFields() as $i => $field) {
            $query[$field] = $state['footer_menus'][$i + 1] ?? '';
        }
        foreach ($state['words'][$locale] ?? [] as $name => $value) {
            $query[ChromeWords::field($name, $locale)] = $value;
        }
        if ($character !== '') {
            $query['character'] = $character;
        }

        return $query;
    }

    /**
     * Each footer column's menu as posted (D-115): '' none, `header`, or a name.
     *
     * @param array<mixed> $fields
     * @return array<int, string>
     */
    private static function footerMenusFrom(array $fields): array
    {
        $menus = [];
        foreach (self::footerMenuFields() as $i => $field) {
            $value = $fields[$field] ?? '';
            $menus[$i + 1] = is_string($value) ? trim($value) : '';
        }

        return $menus;
    }

    /**
     * The other end of query(): what the preview should draw that the site does not have
     * yet, in the shape PageLayoutData::forPreview takes.
     *
     * A query that says nothing about the chrome gets nothing back, and the preview then
     * draws what is stored. That is why `menu` is only set when the request carried it: an
     * absent field is not a choice of "no menu" — only an empty one is.
     *
     * @param array<mixed> $query
     * @return array{look: array<string, string>, character: string, menu?: string, footer_menus?: array<int, string>, words: array<string, string>, bleeds: array<string, string>}
     */
    public static function trying(array $query, string $locale, string $character): array
    {
        $trying = [
            'look' => ChromeLook::fromRequest($query),
            'character' => $character,
            'words' => self::words($query, $locale),
            // Where the chrome renders, which is a design decision and so arrives with the
            // rest of them rather than in the look (D-067).
            'bleeds' => [
                'header_bleed' => is_string($query['header_bleed'] ?? null) ? $query['header_bleed'] : 'sheet',
                'footer_bleed' => is_string($query['footer_bleed'] ?? null) ? $query['footer_bleed'] : 'sheet',
            ],
        ];
        if (array_key_exists(self::MENU, $query) && is_string($query[self::MENU])) {
            $trying['menu'] = $query[self::MENU];
        }
        // Only the columns the request named, for the same reason as the menu above.
        $footerMenus = [];
        foreach (self::footerMenuFields() as $i => $field) {
            if (array_key_exists($field, $query) && is_string($query[$field])) {
                $footerMenus[$i + 1] = $query[$field];
            }
        }
        if ($footerMenus !== []) {
            $trying['footer_menus'] = $footerMenus;
        }

        return $trying;
    }

    /**
     * WHAT EVERY CONTROL COMES TO, in the words the screen shows beside it (PLAN.md D-066).
     *
     * One place, used twice: the screen renders these into the markup, and /check returns
     * them so they follow a control that is being dragged. Before this they were rendered
     * once and went stale the moment anything moved — a readout that lies is worse than no
     * readout, because it is read.
     *
     * @param array<string, string> $decisions validated decisions
     * @return array<string, string> readout name => what it says
     */
    public static function readouts(array $decisions): array
    {
        $readable = Tokens::readable($decisions);
        $readouts = [
            'text_size' => $readable['text']['base'] . 'px',
            'scale' => $decisions['scale'] . '×',
            'spacing' => $readable['space'] . 'px',
            'radius' => $readable['radius'] . 'px',
            'container' => $decisions['container'] . 'rem · ' . $readable['container'] . 'px',
            'sheet_width' => $decisions['sheet_width'] . 'rem · ' . $readable['sheet_width'] . 'px',
            'sheet_gap' => $readable['sheet_gap'] . 'px',
            'phone' => t('design.readable.phone', ['phone' => $readable['text_phone'] . 'px']),
        ];
        foreach (Tokens::NUDGES as $key => $bounds) {
            $readouts[$key] = t('design.nudge_readout', [
                'nudge' => $decisions[$key] . 'px',
                'size' => $readable['text'][$bounds['step']] . 'px',
            ]);
        }
        foreach (['4xl', '2xl', 'base', 'sm'] as $step) {
            $readouts['specimen.' . $step] = $readable['text'][$step] . 'px';
        }

        return $readouts;
    }

    /**
     * Form fields as decisions: a colour counts only when its switch is on, because a colour
     * input ALWAYS submits some colour and "this one is mine" cannot be read off its value.
     *
     * The same rule the second colour has always had, now that five more colours can be the
     * owner's (D-063).
     *
     * @param array<mixed> $fields
     * @return array<mixed>
     */
    public static function decisions(array $fields): array
    {
        if (($fields['use_secondary'] ?? '') !== '1') {
            $fields['secondary'] = '';
        }
        foreach (Palette::BY_HAND as $role) {
            if (($fields['color_' . $role . '_on'] ?? '') !== '1') {
                $fields['color_' . $role] = '';
            }
        }
        foreach (Tokens::OWN_COLOURS as $field) {
            if (($fields[$field . '_on'] ?? '') !== '1') {
                $fields[$field] = '';
            }
        }

        return $fields;
    }

    /**
     * The words one request carries for one language, and only those it actually carries.
     *
     * @param array<mixed> $query
     * @return array<string, string>
     */
    private static function words(array $query, string $locale): array
    {
        $words = [];
        $names = ['button_label', 'button_url', 'small_print'];
        $texts = [];
        foreach (ChromeWords::COLUMN_FIELDS as [$title, $text]) {
            $names[] = $title;
            $names[] = $text;
            $texts[] = $text;
        }
        foreach ($names as $name) {
            $field = ChromeWords::field($name, $locale);
            if (array_key_exists($field, $query) && is_string($query[$field])) {
                // A column's words are rich text (D-113), and the preview draws them as a
                // save would store them: cleaned. Markup the whitelist refuses never reaches
                // the frame, even the owner's own.
                $words[$name] = in_array($name, $texts, true) ? ChromeWords::cleanText($query[$field]) : $query[$field];
            }
        }

        return $words;
    }
}
