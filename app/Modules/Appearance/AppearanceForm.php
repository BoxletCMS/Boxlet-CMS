<?php

namespace App\Modules\Appearance;

use App\Core\Request;
use App\Modules\Design\Vocabulary\Decisions;
use App\Modules\Design\CssNumber;
use App\Modules\Design\Tokens;
use App\Modules\Settings\ChromeLook;

/**
 * What one Appearance screen sends, and what its preview reads back (PLAN.md D-059).
 *
 * TWO SCREENS BECAME ONE, so two forms became one form: the design's decisions and the
 * chrome's look choices. They are saved together, refused together, and previewed together
 * — the header's and footer's words and menus moved to Navigation (D-180) — and both
 * the screen and the preview have to agree, field by field, on what was sent. That agreement
 * lives here rather than in each of them.
 */
final class AppearanceForm
{
    /**
     * Everything a save is trying, checked, errors keyed by the decision at fault. The menus
     * and the words are Navigation's since D-180, and never read or written here.
     *
     * @param list<string> $locales
     * @return array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>, errors: array<string, string>}
     */
    public static function read(Request $request, array $locales): array
    {
        // Both halves are one design since D-164: the header and footer's fields carry their
        // `look_` prefix on the form, and are validated with the rest.
        $fields = self::decisions($request->body);
        foreach (ChromeLook::keys() as $choice) {
            $fields[$choice] = trim($request->input(ChromeLook::field($choice)));
        }
        $design = Tokens::validate($fields);
        $look = array_intersect_key($design['decisions'], array_flip(ChromeLook::keys()));

        return [
            'decisions' => array_diff_key($design['decisions'], $look),
            'look' => $look,
            'dark' => self::dark($request->body),
            'errors' => $design['errors'],
        ];
    }

    /**
     * THE OWNER'S DARK COLOURS (D-187), as the form carries them: `dark_color_text` with its
     * switch `dark_color_text_on`, as every colour by hand. Set while Appearance is in Dark,
     * for dark mode only; '' where there is none.
     *
     * @param array<mixed> $fields
     * @return array<string, string>
     */
    public static function dark(array $fields): array
    {
        $given = [];
        foreach (Decisions::DARK_OWN as $key) {
            if (($fields['dark_' . $key . '_on'] ?? '') === '1') {
                $given[$key] = $fields['dark_' . $key] ?? '';
            }
        }

        return Tokens::darkOwn($given);
    }

    /**
     * The query the preview, the stylesheet and the check endpoints are called with: the
     * whole screen, so the picture is of what is on it rather than of what is stored.
     *
     * The header's and footer's words and menus are not in it: they are Navigation's (D-180),
     * and the preview draws them as stored.
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>} $state
     * @param string $basis the character the screen measures against: the loaded one, else
     *        the site's
     * @return array<string, string>
     */
    public static function query(array $state, string $locale, string $character = '', string $basis = ''): array
    {
        // What the screen SHOWS, every key answered: the picture is of the design as drawn,
        // so a key that follows the character is sent as the character's value — the loaded
        // one's, else the site's own ($basis). Resolved against the default character
        // (Minimal) when none was loaded, the picture showed Minimal's values over a site on
        // any other character (found reviewing phase 1).
        $resolved = Tokens::resolve($state['decisions'] + $state['look'], $character !== '' ? $character : $basis, $state['dark'] ?? []);
        $query = [];
        foreach ($resolved as $key => $value) {
            $query[in_array($key, ChromeLook::keys(), true) ? ChromeLook::field($key) : $key] = $value;
        }
        $query['use_secondary'] = $resolved['secondary'] !== '' ? '1' : '0';
        // Each hand-set colour needs its switch in the query too, or the preview reads a
        // colour the form only carries as a default and draws something nobody chose.
        foreach (Decisions::BY_HAND as $role) {
            $query['color_' . $role . '_on'] = $resolved['color_' . $role] !== '' ? '1' : '0';
        }
        foreach (Decisions::OWN_COLOURS as $field) {
            $query[$field . '_on'] = $resolved[$field] !== '' ? '1' : '0';
        }
        // The owner's dark colours, which the design drawn in light mode does not show.
        foreach (Tokens::darkOwn($state['dark'] ?? []) as $key => $value) {
            $query['dark_' . $key] = $value;
            $query['dark_' . $key . '_on'] = $value !== '' ? '1' : '0';
        }
        if ($character !== '') {
            $query['character'] = $character;
        }

        return $query;
    }

    /**
     * Values as the form's fields carry them (D-181): a chrome choice under its `look_` name,
     * and each colour that can be the owner's with its switch. What the bar's count of
     * unpublished changes compares the screen against.
     *
     * @param array<string, string> $shown every key as the controls show it
     * @param array<string, string> $dark the owner's dark colours, the switch of each with it
     * @return array<string, string>
     */
    public static function fields(array $shown, array $dark = []): array
    {
        $fields = [];
        foreach ($shown as $key => $value) {
            $fields[in_array($key, ChromeLook::keys(), true) ? ChromeLook::field($key) : $key] = $value;
        }
        $fields['use_secondary'] = ($shown['secondary'] ?? '') !== '' ? '1' : '0';
        foreach (Decisions::BY_HAND as $role) {
            $fields['color_' . $role . '_on'] = ($shown['color_' . $role] ?? '') !== '' ? '1' : '0';
        }
        foreach (Decisions::OWN_COLOURS as $field) {
            $fields[$field . '_on'] = ($shown[$field] ?? '') !== '' ? '1' : '0';
        }
        // Every one, set or not: the bar counts the keys it is handed, and a dark colour set on
        // the screen over none published is a change.
        foreach (Tokens::darkOwn($dark) as $key => $value) {
            $fields['dark_' . $key . '_on'] = $value !== '' ? '1' : '0';
            $fields['dark_' . $key] = $value;
        }

        return $fields;
    }

    /**
     * The other end of query(): what the preview should draw that the site does not have
     * yet, in the shape PageLayoutData::forPreview takes. The menus and words are not in it,
     * so the preview draws them as stored (D-180).
     *
     * @param array<mixed> $query
     * @return array{look: array<string, string>, character: string, bleeds: array<string, string>}
     */
    public static function trying(array $query, string $locale, string $character): array
    {
        return [
            'look' => ChromeLook::fromRequest($query),
            'character' => $character,
            // Where the chrome renders, which is a design decision and so arrives with the
            // rest of them rather than in the look (D-067).
            'bleeds' => [
                'header_bleed' => is_string($query['header_bleed'] ?? null) ? $query['header_bleed'] : 'sheet',
                'footer_bleed' => is_string($query['footer_bleed'] ?? null) ? $query['footer_bleed'] : 'sheet',
            ],
        ];
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
        $readouts = [];
        // Every number, in what a person can picture: pixels, or rem with its pixels beside.
        foreach (Decisions::ALL as $key => $definition) {
            $value = $decisions[$key] ?? '';
            if ($definition['type'] !== 'number' || $value === '') {
                continue;
            }
            $readouts[$key] = match ($definition['unit'] ?? '') {
                'rem' => $value . 'rem · ' . CssNumber::of((float) $value * 16) . 'px',
                'px' => $value . 'px',
                '%' => $value . '%',
                'em' => $value . 'em',
                default => $value,
            };
        }
        // A button's corners at their top are a pill (Derived::radii draws 999px), and the
        // readout says so rather than a number the button does not have.
        if (($decisions['button_radius'] ?? '') !== '' && (float) $decisions['button_radius'] >= 28) {
            $readouts['button_radius'] = t('design.button_radius.pill');
        }
        $readouts['scale'] = t('design.scale_readout', ['scale' => $decisions['scale'], 'size' => $readable['text']['4xl'] . 'px']);
        $readouts['sheet_gap'] = $readable['sheet_gap'] . 'px';
        $readouts['phone'] = t('design.readable.phone', ['phone' => $readable['text_phone'] . 'px']);
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
        // Unticked, the second colour is NONE — which settles to '' where the character has
        // none either (Overrides::settle).
        if (($fields['use_secondary'] ?? '') !== '1') {
            $fields['secondary'] = 'none';
        }
        foreach (Decisions::BY_HAND as $role) {
            if (($fields['color_' . $role . '_on'] ?? '') !== '1') {
                $fields['color_' . $role] = '';
            }
        }
        foreach (Decisions::OWN_COLOURS as $field) {
            if (($fields[$field . '_on'] ?? '') !== '1') {
                $fields[$field] = '';
            }
        }

        return $fields;
    }
}
