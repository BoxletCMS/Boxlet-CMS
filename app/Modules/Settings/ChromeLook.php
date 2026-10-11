<?php

namespace App\Modules\Settings;

use App\Core\Db;
use App\Modules\Design\Characters;
use App\Modules\Design\Composition;
use App\Modules\Design\Design;
use App\Modules\Design\Vocabulary\Decisions;

/**
 * How the header and footer look (PLAN.md D-032, D-036, D-164): the global decisions whose
 * part is `look`, each with a value the character supplies and an override the owner may set.
 *
 * ONE STORE SINCE D-164. The look was a set of settings of its own; it is now the header and
 * footer half of the design (Design, design_tokens), defined in the same vocabulary, stored
 * the same way — '' follows the character — and changed by the same screen. This class is
 * what reads that half for the chrome.
 */
final class ChromeLook
{
    /**
     * Every look key, in the vocabulary's order.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return Decisions::keys('look');
    }

    /**
     * What the owner chose, '' for every choice left to the character.
     *
     * @return array<string, string>
     */
    public static function stored(Db $db): array
    {
        return array_intersect_key(Design::load($db), array_flip(self::keys()));
    }

    /**
     * Every look key checked against the vocabulary, '' for anything it does not take.
     *
     * @param array<mixed> $raw key => value
     * @return array<string, string>
     */
    public static function clean(array $raw): array
    {
        $look = [];
        foreach (self::keys() as $key) {
            $value = $raw[$key] ?? '';
            $look[$key] = Decisions::clean($key, is_int($value) || is_float($value) ? (string) $value : $value) ?? '';
        }

        return $look;
    }

    /**
     * The look to draw, in three levels: what is being TRIED, then what the owner SAVED,
     * then what the CHARACTER gives.
     *
     * @param array<string, string> $overrides key => value, '' meaning follow the character
     * @return array<string, string>
     */
    public static function resolve(Db $db, array $overrides = [], string $character = ''): array
    {
        $defaults = Characters::look($character !== '' ? $character : Composition::active($db));
        $look = [];
        foreach (self::stored($db) as $name => $stored) {
            $value = $overrides[$name] ?? $stored;
            $look[$name] = $value !== '' ? $value : $defaults[$name];
        }

        return $look;
    }

    /** The form field that carries one choice. A field name is not a settings key. */
    public static function field(string $choice): string
    {
        return 'look_' . $choice;
    }

    /**
     * The look choices a request is trying, for a preview: ONLY THE ONES THE REQUEST NAMED.
     * A request silent about a choice means the owner's saved one, not a reset. Reads a
     * request and writes nothing.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function fromRequest(array $input): array
    {
        $look = [];
        foreach (self::keys() as $name) {
            if (!array_key_exists(self::field($name), $input)) {
                continue;
            }
            $value = $input[self::field($name)];
            $look[$name] = Decisions::clean($name, $value) ?? '';
        }

        return $look;
    }

    /**
     * Writes the owner's choices into the design, leaving its other keys as they are, without
     * compiling: the Appearance screen saves both halves with Design::save() and compiles once.
     *
     * @param array<string, string> $values
     */
    public static function save(Db $db, array $values): void
    {
        Design::store($db, array_replace(Design::load($db), self::clean($values)));
    }
}
