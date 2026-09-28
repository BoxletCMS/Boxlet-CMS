<?php

namespace App\Modules\Forms;

/**
 * What a visitor just sent to one form, for the one request that draws the page again with
 * it: the fields refused and what was typed in them, or a notice such as "too many
 * messages". Set by the submit route before it asks the page to draw, read by FormBlocks.
 *
 * Static because it lives exactly one request — the page render that follows a refused
 * send — and no session carries it: visitors have none, and a form must not start one.
 */
final class FormState
{
    /** @var array<int, array{errors: array<string, string>, old: array<string, string>, notice: string}> */
    private static array $forms = [];

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    public static function set(int $formId, array $errors, array $old, string $notice = ''): void
    {
        self::$forms[$formId] = ['errors' => $errors, 'old' => $old, 'notice' => $notice];
    }

    /**
     * @return array{errors: array<string, string>, old: array<string, string>, notice: string}
     */
    public static function for(int $formId): array
    {
        return self::$forms[$formId] ?? ['errors' => [], 'old' => [], 'notice' => ''];
    }

    /** Forgets everything; tests dispatch many requests in one process. */
    public static function clear(): void
    {
        self::$forms = [];
    }
}
