<?php

namespace App\Modules\Appearance;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Design\Characters;
use App\Modules\Design\Composition;
use App\Modules\Design\Design;
use App\Modules\Design\Presets;
use App\Modules\Settings\ChromeLook;
use App\Support\Url;

/**
 * ONE SCREEN FOR HOW THE SITE LOOKS (PLAN.md D-059): the character, the ten decisions, the
 * header and footer, and a picture of all of it.
 *
 * It replaces two screens that were one screen cut in half. Header & footer had seven
 * choices and NO PICTURE; Design had a picture that deliberately drew no header or footer.
 * Each was incomplete in exactly the way the other would have fixed, and the owner had to
 * hold the result in their head while moving between them.
 *
 * Saving is still one ordinary form post that works without JavaScript, and applying a
 * character to a site that already has blocks still offers two explicit buttons, because
 * the second overwrites section styles chosen by hand (SPEC §5.4).
 *
 * PUBLISHING IS WHAT IS LEFT HERE (D-157). What the screen shows is AppearanceScreen, and
 * what its other buttons do is AppearanceActions: the three were 444 lines in one file.
 */
final class AppearanceController
{
    private readonly AppearanceScreen $screen;

    public function __construct(private readonly Container $container)
    {
        $this->screen = new AppearanceScreen($container);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        return $this->screen->render($this->screen->published(), [], null);
    }

    /**
     * Publish, or one of the screen's own buttons. Those change nothing on the site: Publish
     * is the confirmation.
     *
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $action = $request->input('action');
        $state = AppearanceForm::read($request, $this->screen->locales());
        $character = $request->input('character');
        $character = Presets::exists($character) ? $character : '';
        $db = $this->db();
        $basis = $character !== '' ? $character : Composition::active($db);
        // A value equal to what it would follow is "as the character has it" (D-159, D-164):
        // the form posts what every control shows, which for a key nobody touched is the
        // character's or the pairing's value.
        $state['decisions'] = Overrides::settle($state['decisions'], $basis);
        $state['look'] = Overrides::settle($state['look'], $basis);
        // A design as a file (D-152): export what is on the screen, load an import into it,
        // delete an imported character. Its own controller; this one only hands it the screen.
        $transfer = (new DesignTransferController($this->container))->fromScreen(
            $action,
            $state,
            $character,
            /**
             * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>} $state
             * @param array<string, string> $errors
             */
            fn (array $state, array $errors, ?string $notice, int $status, string $character): Response => $this->screen->render($state, $errors, $notice, $status, $character),
        );
        if ($transfer !== null) {
            return $transfer;
        }
        $answered = (new AppearanceActions($this->container, $this->screen))->handle($request, $action, $state, $character, $basis);
        if ($answered !== null) {
            return $answered;
        }

        if ($state['errors'] !== []) {
            return $this->screen->render($state, $state['errors'], t('design.not_saved'), 422, $character);
        }

        /*
         * PUBLISH ASKS ONCE, when the answer is destructive (D-068, handoff §2.1).
         *
         * Applying a character to a site that already has blocks can rewrite every section's
         * style, so that has always needed two explicit buttons. They used to sit
         * in the bar permanently — three buttons for a choice that matters on the rare
         * publish after loading a character — so the bar is one Publish now and the question
         * is asked at the moment it applies.
         */
        if ($action === 'save' && $character !== '' && Composition::hasBlocks($db)) {
            return $this->screen->render($state, [], null, 200, $character, ['confirm' => true, 'restyled' => Composition::styledByHand($db), 'ownLayouts' => Composition::ownLayouts($db, $this->container->get('blocks'))]);
        }
        $composing = $action === 'save_composition';

        // The character first: what the owner's values are drawn over is the one now chosen,
        // and the stylesheet is compiled once, for both halves of the design (D-164).
        if ($character !== '') {
            Composition::remember($db, $character);
        }
        Design::save($db, $state['decisions'] + $state['look'], (string) $this->container->get('config')->get('app.cache_path'), $state['dark'] ?? []);
        // The header's and footer's words and menus are Navigation's (D-180): nothing here
        // writes them, so publishing a design never takes them away (appearance_navigation_test).

        $message = t('appearance.published');
        if ($character !== '') {
            if ($composing) {
                $count = Composition::apply($db, $this->container->get('blocks'), $character);
                $message = t('design.saved_with_composition', [
                    'count' => $count,
                    'character' => Characters::label($character),
                ]);
            }
        }
        Activity::record($db, 'design', 'saved', null, $character !== '' ? Characters::label($character) : '');
        $session = $this->container->get('session');
        $session->set('flash', $message);
        $session->set('flash_kind', 'success');

        return Response::redirect(Url::admin('appearance'));
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
