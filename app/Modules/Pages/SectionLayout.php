<?php

namespace App\Modules\Pages;

/**
 * How many columns a section has, in what proportion, and what they do on a phone
 * (PLAN.md D-093 step 3, migration 0027).
 *
 * A CLOSED SET, NEVER A PERCENTAGE — the same argument SectionStyle makes about colour.
 * A column at 37% takes the design system's guarantee with it: it cannot promise a
 * readable measure, it cannot say what it does when the screen halves, and it turns a
 * decision the character should make into a number the owner has to get right. Seven
 * arrangements cover what a page actually needs, and every one of them has a rule in
 * sections.css that says how it collapses.
 *
 * THE PROPORTIONS LIVE HERE and the grid tracks live in sections.css, because CSS is not
 * generated from PHP in this project. That is two declarations of one fact, so a test
 * reads the stylesheet and refuses a layout whose tracks disagree with this list — the
 * kind of duplication that is safe only while something checks it.
 */
final class SectionLayout
{
    /** The only layout a section had before columns, and the one every migrated row has. */
    public const ONE = 'one';

    /**
     * Layout name => the relative width of each column, in order.
     *
     * The count is what the editor and the renderer ask for; the weights are what the
     * panel draws as a little diagram, so the owner picks a shape rather than a word.
     *
     * @var array<string, list<int>>
     */
    public const LAYOUTS = [
        self::ONE => [1],
        'halves' => [1, 1],
        'thirds' => [1, 1, 1],
        'quarters' => [1, 1, 1, 1],
        // Two columns that are deliberately unequal: a wide column of words with a narrow
        // one beside it is the arrangement a page wants most often, and two halves is not
        // it — halves give a heading the same room as a caption.
        'wide-left' => [2, 1],
        'wide-right' => [1, 2],
        // Narrower still: content with a rail of links or a small form beside it.
        'sidebar' => [3, 1],
    ];

    /**
     * What the columns do when the screen is too narrow to hold them side by side.
     *
     * `reverse` earns its place for the reason D-093 gives: a picture to the left of text
     * reads correctly wide and wrongly stacked, because the picture arrives before the
     * words it illustrates. Every other responsive wish is the character's decision.
     */
    public const STACKS = ['stack', 'stay', 'reverse'];

    public const DEFAULT_STACK = 'stack';

    public static function normalize(mixed $layout): string
    {
        return is_string($layout) && isset(self::LAYOUTS[$layout]) ? $layout : self::ONE;
    }

    public static function normalizeStack(mixed $stack): string
    {
        return is_string($stack) && in_array($stack, self::STACKS, true) ? $stack : self::DEFAULT_STACK;
    }

    /**
     * How many columns this layout has. An unknown name is one column, which is the
     * layout every section had before this and the only answer that cannot lose a block.
     */
    public static function columns(mixed $layout): int
    {
        return count(self::LAYOUTS[self::normalize($layout)]);
    }

    /**
     * The column a block belongs in, clamped to what its section actually has.
     *
     * Asked on render, not only on save: a section whose layout was narrowed from four
     * columns to two still holds blocks that remember column 3, and they have to be drawn
     * somewhere. The last column is where they go, so nothing ever silently disappears —
     * and the stored value is left alone, so widening the section again puts them back.
     */
    public static function clamp(int $column, mixed $layout): int
    {
        $last = self::columns($layout) - 1;

        return $column < 0 ? 0 : min($column, $last);
    }

    /**
     * The class names the column container wears.
     *
     * `cols-` and not `columns-`, which the `columns` block has owned since D-036 and
     * would collide with inside a section holding one.
     *
     * @return list<string>
     */
    public static function classes(string $layout, string $stack): array
    {
        return ['section-cols', 'cols-' . self::normalize($layout), 'stack-' . self::normalizeStack($stack)];
    }
}
