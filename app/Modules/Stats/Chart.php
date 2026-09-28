<?php

namespace App\Modules\Stats;

/**
 * The trend chart, drawn on the server as SVG (PLAN.md D-051):
 * no chart library, no script.
 *
 * The SVG holds only the lines, stretched to its box (preserveAspectRatio="none") with
 * strokes that do not stretch with it. The numbers on the axes are HTML beside it, so they
 * stay readable at a phone's width, where a scaled SVG would shrink its text to nothing.
 * Colours come from classes in admin-stats.css: no style attribute (the admin's CSP) and no
 * literal colour (SPEC §5.4).
 *
 * The exact figures for each point are in a <title> on a column the width of that point, a
 * tooltip on hover; the screen puts the same figures in a table for everyone else.
 */
final class Chart
{
    /**
     * @param list<array{day: string, visitors: int, views: int}> $series
     * @param callable(string): string $label a point's day as the axis and tooltip show it
     */
    public static function trend(array $series, callable $label): string
    {
        $count = count($series);
        if ($count === 0) {
            return '';
        }
        $step = self::step(max(array_column($series, 'views')));
        $top = $step * 3;

        $svg = '<svg class="stats-chart-lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">';
        foreach ([0, 1, 2, 3] as $n) {
            $y = self::num(100 - $n * 100 / 3);
            $svg .= '<line class="stats-grid" x1="0" x2="100" y1="' . $y . '" y2="' . $y . '" vector-effect="non-scaling-stroke"/>';
        }
        $visitors = self::points($series, 'visitors', $top);
        $svg .= '<path class="stats-area" d="M0,100 L' . implode(' L', $visitors) . ' L100,100 Z"/>';
        $svg .= '<polyline class="stats-line-views" points="' . implode(' ', self::points($series, 'views', $top)) . '" vector-effect="non-scaling-stroke"/>';
        $svg .= '<polyline class="stats-line-visitors" points="' . implode(' ', $visitors) . '" vector-effect="non-scaling-stroke"/>';

        // A column around each point, halfway to its neighbours, for the tooltip.
        $x = static fn (int $i): float => $count === 1 ? 50 : $i * 100 / ($count - 1);
        foreach ($series as $i => $point) {
            $left = $i === 0 ? 0 : ($x($i - 1) + $x($i)) / 2;
            $right = $i === $count - 1 ? 100 : ($x($i) + $x($i + 1)) / 2;
            $svg .= '<rect class="stats-hit" x="' . self::num($left) . '" y="0" width="' . self::num($right - $left) . '" height="100">'
                . '<title>' . e($label($point['day']) . ' — ' . t('stats.visitors') . ': ' . $point['visitors'] . ', ' . t('stats.views') . ': ' . $point['views']) . '</title></rect>';
        }
        $svg .= '</svg>';

        $axis = '<ol class="stats-chart-y" aria-hidden="true">';
        foreach ([3, 2, 1, 0] as $n) {
            $axis .= '<li>' . e(number_format($n * $step)) . '</li>';
        }
        $axis .= '</ol>';

        $marks = array_unique([0, intdiv($count - 1, 2), $count - 1]);
        $days = '<div class="stats-chart-x" aria-hidden="true">';
        foreach ($marks as $i) {
            $days .= '<span>' . e($label($series[$i]['day'])) . '</span>';
        }
        $days .= '</div>';

        return '<div class="stats-chart-plot">' . $axis . $svg . '</div>' . $days;
    }

    /**
     * The step between gridlines: the smallest of 1, 2 or 5 times a power of ten that puts
     * the highest value under the top of three steps, so every label is a whole number.
     */
    public static function step(int $max): int
    {
        for ($power = 1; ; $power *= 10) {
            foreach ([1, 2, 5] as $factor) {
                if ($factor * $power * 3 >= $max) {
                    return $factor * $power;
                }
            }
        }
    }

    /**
     * @param list<array{day: string, visitors: int, views: int}> $series
     * @param 'visitors'|'views' $key
     * @return list<string> "x,y" in the 100 by 100 box
     */
    private static function points(array $series, string $key, int $top): array
    {
        $count = count($series);
        $points = [];
        foreach ($series as $i => $point) {
            $x = $count === 1 ? 50 : $i * 100 / ($count - 1);
            $points[] = self::num($x) . ',' . self::num(100 - $point[$key] * 100 / max(1, $top));
        }

        return $points;
    }

    private static function num(float|int $value): string
    {
        $text = number_format((float) $value, 2, '.', '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
