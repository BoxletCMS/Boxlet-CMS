<?php

namespace App\Modules\Appearance;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Design\Characters;
use App\Modules\Design\Composition;
use App\Modules\Design\Palette;
use App\Modules\Design\PalettePairs;
use App\Modules\Design\Presets;
use App\Modules\Design\Tokens;
use App\Modules\Design\Vocabulary\Decisions;
use App\Modules\Settings\ChromeLook;

/**
 * WHAT THE APPEARANCE SCREEN'S BUTTONS DO, short of publishing (PLAN.md D-059, D-157): load a
 * character, keep or bring back a design, give a colour back to the palette, put a control,
 * a section or everything back as the character has it.
 *
 * Split from AppearanceController with the screen (AppearanceScreen), so that one is left
 * with publishing. NONE OF THESE TOUCHES THE SITE: each answers with the screen in its new
 * state, and Publish is still the one confirmation. A redirect would hand back the
 * PUBLISHED design and lose the work on the screen.
 */
final class AppearanceActions
{
    public function __construct(private readonly Container $container, private readonly AppearanceScreen $screen)
    {
    }

    /**
     * The screen after one of these actions, or null for an action that is not one of them.
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>, errors: array<string, string>} $state
     * @param string $character the character loaded into the form, '' when none
     * @param string $basis what the screen measures against: that character, else the active one
     */
    public function handle(Request $request, string $action, array $state, string $character, string $basis): ?Response
    {
        // The screen as it was, and nothing else: an action this file did not know would be a
        // publish, and a page reached with `keep` from an older screen must not be one.
        if ($action === 'keep') {
            return $this->screen->render($state, [], null, 200, $character);
        }
        if (str_starts_with($action, 'preset:')) {
            return $this->load(substr($action, strlen('preset:')), $state);
        }
        if (str_starts_with($action, 'library:')) {
            return $this->library($request, $action, $state, $character);
        }
        if (str_starts_with($action, 'colour:light:')) {
            return $this->light(substr($action, strlen('colour:light:')), $state, $character);
        }
        if (str_starts_with($action, 'colour:free')) {
            return $this->free($action, $state, $character, $basis);
        }
        if (str_starts_with($action, 'reset:')) {
            $scope = substr($action, strlen('reset:'));
            $reset = Overrides::reset($state['decisions'] + $state['look'], $scope);
            if ($reset === null) {
                return $this->screen->render($state, [], null, 404, $character);
            }
            $again = Tokens::validate($reset, $basis);
            // Everything, or the colours, put back: the owner's dark colours with them (D-187).
            if ($scope === 'all' || $scope === 'section:colours') {
                $state['dark'] = [];
            }

            return $this->screen->render(
                self::split($again['decisions']) + $state,
                $again['errors'],
                t($action === 'reset:all' ? 'inspector.reset.all_done' : 'inspector.reset.done'),
                200,
                $character,
            );
        }

        return null;
    }

    /**
     * LOADING A CHARACTER KEEPS THE OWNER'S CHANGES (D-164; the rebuild's README 1.1). Every
     * value the owner set stays; only what follows the character changes, with it. Their words
     * and menus are not the character's either. Publish is still the confirmation.
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>, errors: array<string, string>} $state
     */
    private function load(string $name, array $state): Response
    {
        if (!Presets::exists($name)) {
            return $this->screen->render($state, [], null, 404, '');
        }

        return $this->screen->render($state, [], t('design.preset_loaded', ['preset' => Characters::label($name)]), 200, $name);
    }

    /**
     * The owner's values in the two halves the screen's state keeps them in.
     *
     * @param array<string, string> $values
     * @return array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>}
     */
    private static function split(array $values): array
    {
        $look = array_intersect_key($values, array_flip(ChromeLook::keys()));

        return ['decisions' => array_diff_key($values, $look), 'look' => $look];
    }

    /**
     * GIVING A COLOUR BACK TO THE PALETTE (D-074), one, all, or the ones that fail (D-160).
     *
     * AN ACTION, NOT A BOX TO UNTICK. Each hand-set colour needs a switch beside it in the
     * form, because a colour input always carries SOME colour and "is this mine" cannot be
     * read off its value (D-065) — but that is a mechanism, not something to put in front of
     * a person. RE-VALIDATED, NOT TRUSTED: the palette's own colour can fail a pair the
     * owner's colour passed, and the screen must keep saying so (D-063).
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>, errors: array<string, string>} $state
     */
    private function free(string $action, array $state, string $character, string $basis): Response
    {
        $decisions = $state['decisions'];
        $dark = Tokens::darkOwn($state['dark'] ?? []);
        $freed = 0;
        if ($action === 'colour:free:failing') {
            /* ONLY WHAT THE OWNER SET AND WHAT FAILS. A failing pair names the control that
               can fix it (PalettePairs::pairs), and that is a colour by hand whenever one of its
               two colours is. Freed one at a time and measured again, because freeing the
               ink can be enough and the background the owner chose should then stay theirs.
               A pair the main colour fails is left: no colour by hand caused it, and the
               screen says to change the main colour instead. */
            while (($culprit = self::failingByHand(Tokens::resolve($decisions + $state['look'], $basis, $dark))) !== null) {
                // In dark mode the owner's dark colour is the one drawn, and the one freed (D-187).
                if (($decisions['mode'] ?? '') === 'dark' && ($dark[$culprit] ?? '') !== '') {
                    $dark[$culprit] = '';
                } else {
                    $decisions[$culprit] = '';
                }
                $freed++;
            }
        } else {
            /* The seven palette roles and the three places that may take a colour of their
               own (D-076) are one list here: "Free all" frees all ten. */
            $named = $action === 'colour:free' ? null : substr($action, strlen('colour:free:'));
            // All of them: the owner's dark colours too (D-187).
            if ($named === null) {
                $dark = Tokens::darkOwn([]);
            }
            foreach (array_merge(array_map(static fn (string $role): string => 'color_' . $role, Decisions::BY_HAND), Decisions::OWN_COLOURS) as $field) {
                if ($named === null || $named === $field || $named === substr($field, strlen('color_'))) {
                    $decisions[$field] = '';
                    $freed++;
                }
            }
        }
        $again = Tokens::validate($decisions + $state['look'], $basis);

        return $this->screen->render(
            ['dark' => $dark] + self::split($again['decisions']) + $state,
            $again['errors'],
            $freed === 0 ? null : t($freed > 1 ? 'design.by_hand.all_freed' : 'design.by_hand.freed'),
            200,
            $character,
        );
    }

    /**
     * "USE LIGHT VALUE" (D-187, the owner): a colour set while Appearance was in Dark given up,
     * and dark mode draws what it would have without it — the colour held for both modes, the
     * character's dark version, the palette's.
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>, errors: array<string, string>} $state
     */
    private function light(string $role, array $state, string $character): Response
    {
        // A palette role by its name (`text`), the header's or footer's colour by its key.
        $key = in_array($role, Decisions::DARK_OWN, true) ? $role : 'color_' . $role;
        if (!in_array($key, Decisions::DARK_OWN, true)) {
            return $this->screen->render($state, [], null, 404, $character);
        }
        $dark = Tokens::darkOwn($state['dark'] ?? []);
        $dark[$key] = '';

        return $this->screen->render(['dark' => $dark] + $state, $state['errors'], t('design.dark.light_used'), 200, $character);
    }

    /**
     * The first colour by hand that a failing pair blames, or null when none does.
     *
     * @param array<string, string> $decisions the design as drawn
     */
    public static function failingByHand(array $decisions): ?string
    {
        $byHand = Tokens::byHand($decisions);
        foreach (PalettePairs::failures(Palette::forDecisions($decisions), $decisions['secondary'] !== '', $byHand, Tokens::ownChrome($decisions)) as $failure) {
            if (Overrides::kind($failure['decision']) === 'by_hand' && ($decisions[$failure['decision']] ?? '') !== '') {
                return $failure['decision'];
            }
        }

        return null;
    }

    /**
     * The library (D-061): keep what is on the screen, bring one back, throw one away.
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>, errors: array<string, string>} $state
     */
    private function library(Request $request, string $action, array $state, string $character): Response
    {
        $db = $this->db();
        // A kept design is the owner's values over a character, and '' in it means that
        // character's (D-164): so it keeps the one it was made over — the one loaded on the
        // screen, else the site's. Without it a kept design came back over whichever
        // character was active by then, and its colours with it.
        $over = $character !== '' ? $character : Composition::active($db);

        // Overwriting from a design's own row: the name comes from the design itself, so
        // "save what is on screen into this one" needs no field and cannot be mistyped.
        if (str_starts_with($action, 'library:save:')) {
            $into = DesignLibrary::find($db, (int) substr($action, strlen('library:save:')));
            if ($into === null) {
                return $this->screen->render($state, [], null, 404, $character);
            }
            DesignLibrary::save($db, $into['name'], $state['decisions'], $state['look'], $over, $state['dark'] ?? []);
            Activity::record($db, 'design', 'kept', null, $into['name']);

            return $this->screen->render($state, [], t('appearance.library.overwritten', ['name' => $into['name']]), 200, $character);
        }

        if ($action === 'library:save') {
            $name = DesignLibrary::cleanName($request->input('library_name'));
            if ($name === '') {
                return $this->screen->render($state, ['library_name' => t('appearance.library.name_needed')], null, 422, $character);
            }
            $written = DesignLibrary::exists($db, $name);
            DesignLibrary::save($db, $name, $state['decisions'], $state['look'], $over, $state['dark'] ?? []);
            Activity::record($db, 'design', 'kept', null, $name);

            return $this->screen->render($state, [], t($written ? 'appearance.library.overwritten' : 'appearance.library.saved', ['name' => $name]), 200, $character);
        }

        $id = (int) substr($action, (int) strrpos($action, ':') + 1);
        $saved = DesignLibrary::find($db, $id);
        if ($saved === null) {
            return $this->screen->render($state, [], null, 404, $character);
        }

        if (str_starts_with($action, 'library:delete:')) {
            DesignLibrary::delete($db, $id);
            Activity::record($db, 'design', 'deleted', null, $saved['name']);

            return $this->screen->render($state, [], t('appearance.library.deleted', ['name' => $saved['name']]), 200, $character);
        }

        // Using one fills the screen with it: the design AND the header and footer it was
        // kept with. The menu and the words stay the site's own.
        return $this->screen->render(
            ['decisions' => $saved['decisions'], 'look' => $saved['look'], 'dark' => $saved['dark']] + $state,
            [],
            t('appearance.library.loaded', ['name' => $saved['name']]),
            200,
            $saved['character'],
        );
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
