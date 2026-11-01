<?php

namespace App\Modules\Design;

use App\Core\Db;
use App\Modules\Design\Vocabulary\Decisions;
use App\Modules\Settings\ChromeLook;

/**
 * A DESIGN AS A SET FILE (PLAN.md D-152, O-78): what Appearance's Export saves of the design on
 * the screen (DesignTransferController), and what an AI client is given as the site's design
 * (McpSite). Split from DesignSet, which reads files and writes their envelope: this decides
 * what a design IS as a file — its character's composition and look, its dark mode.
 */
final class DesignExport
{
    /**
     * A design as a set file: the owner's values over $character, with the character's
     * composition and look where it has them and the dark mode the two make together. What
     * Appearance's Export saves (DesignTransferController) and what an AI client is given as the
     * site's design (McpSite, PLAN.md O-78).
     *
     * @param array<string, string> $decisions
     * @param array<string, string> $look
     * @param array<string, string> $ownDark the owner's colours for dark mode only
     */
    public static function ofDesign(string $id, string $name, array $decisions, array $look, string $character, array $ownDark = []): string
    {
        $dark = Characters::exists($character) ? Characters::dark($character) : [];
        $ownDark = array_filter(Tokens::darkOwn($ownDark), static fn (string $v): bool => $v !== '');
        if ($dark !== [] || $ownDark !== []) {
            // On the site a value the owner holds for both modes stands over the character's
            // dark one; in the file the dark version says so, or it would win there.
            foreach (Decisions::DARK as $key) {
                if (($decisions[$key] ?? '') !== '') {
                    $dark[$key] = $decisions[$key];
                }
            }
            $dark = $ownDark + $dark;
        }
        $composition = null;
        if (Characters::exists($character)) {
            $composition = Characters::composition($character);
            foreach (Characters::look($character) as $choice => $given) {
                $look[$choice] = ($look[$choice] ?? '') !== '' ? $look[$choice] : $given;
            }
        }

        return DesignSet::export($id, ['en' => $name], [], $decisions, $look, $composition, '', [], $dark);
    }

    /** The design the site is published with, as a set file. */
    public static function forSite(Db $db, string $id, string $name): string
    {
        return self::ofDesign($id, $name, Design::load($db), ChromeLook::stored($db), Composition::active($db), Design::dark($db));
    }
}
