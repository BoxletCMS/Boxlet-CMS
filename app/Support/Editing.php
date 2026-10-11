<?php

namespace App\Support;

use Closure;

/**
 * WHETHER THIS DRAWING IS THE BUILDER'S CANVAS, for the templates' few editor marks (PLAN.md
 * D-178, README 1.4 and 4.4). A template calls edit_attr() on the element that shows a field;
 * on a visitor's page that is nothing at all, in the canvas it names the field, so the builder
 * can make that element editable where it stands.
 *
 * A drawing-wide state rather than one more argument to every template: the marks are a
 * property of where the page is drawn, the fifteen templates would otherwise all carry it,
 * and the state is set and put back around one drawing (during()), so a visitor's page drawn
 * in the same request — the page cache, a preview — never carries a mark.
 */
final class Editing
{
    private static bool $on = false;

    private static string $type = '';

    /**
     * $draw run with the marks on or off, and the state as it was afterwards.
     *
     * @template T
     * @param Closure(): T $draw
     * @return T
     */
    public static function during(bool $on, Closure $draw): mixed
    {
        $was = self::$on;
        self::$on = $on;
        try {
            return $draw();
        } finally {
            self::$on = $was;
        }
    }

    /**
     * $draw run for one block of $type: whose field labels a placeholder reads.
     *
     * @template T
     * @param Closure(): T $draw
     * @return T
     */
    public static function block(string $type, Closure $draw): mixed
    {
        $was = self::$type;
        self::$type = $type;
        try {
            return $draw();
        } finally {
            self::$type = $was;
        }
    }

    public static function on(): bool
    {
        return self::$on;
    }

    public static function type(): string
    {
        return self::$type;
    }
}
