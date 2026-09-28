<?php

namespace App\Modules\Stats;

use App\Support\Url;

/**
 * The world map on the Statistics screen (PLAN.md O-20): every country a path in
 * public/assets/vendor/world-map.svg, shaded by how many visitors came from it, and a link
 * that narrows the screen to that country.
 *
 * Drawn on the server, like the trend chart: no map library, no script, no tiles fetched
 * from anyone. The shading is five classes in admin-stats-map.css — a fill in a style
 * attribute would be refused by the admin's CSP.
 *
 * The map is inlined rather than put in an <img> because an image cannot carry a link per
 * country, a tooltip per country, or the admin's colours.
 */
final class Map
{
    public const FILE = 'assets/vendor/world-map.svg';

    /** How many steps of shading there are; a country with none keeps the quiet fill. */
    private const STEPS = 5;

    /** A city's dot, in the map's own units: the smallest worth seeing, and the busiest. */
    private const MARK_SMALL = 1.6;

    private const MARK_LARGE = 7.0;

    /**
     * How far in the map will go. MEASURED, not chosen: cut to Croatia alone the map is
     * eight per cent of the world's width, and at that magnification the 1:110m shapes
     * Boxlet ships are a blocky cartoon — a coastline of six straight lines. At eight times
     * they still read as themselves, and a degree of longitude is about seventeen pixels,
     * which is far enough apart for two cities an hour's drive from each other.
     */
    private const MOST_ZOOM = 8.0;

    /**
     * @param array<string, int> $visitors ISO code (upper case) => visitors
     * @param callable(string): string $link where a country's own view lives
     * @param list<array{city: string, country: string, visitors: int, latitude: float, longitude: float}> $cities
     *        the cities to mark, where the owner counts them (D-055)
     * @param string $zoom an ISO code to show the map cut to, or '' for the whole world
     */
    public static function draw(string $file, array $visitors, callable $link, array $cities = [], string $zoom = ''): string
    {
        // Asked before it is read: an error handler that ignores @ turns a missing file into
        // an exception, and a site without the map file simply shows the table instead.
        $svg = is_file($file) ? (string) file_get_contents($file) : '';
        if ($svg === '') {
            return '';
        }
        $most = max([1, ...array_values($visitors)]);
        $boxes = [];

        $svg = (string) preg_replace_callback(
            // data-box is optional: a map drawn before boxes existed still shades, and only
            // loses the ability to be cut to one country.
            '~<path id="c-([A-Z]{2})" data-name="([^"]*)"(?: data-box="([^"]*)")? (d="[^"]*")/>~',
            static function (array $path) use ($visitors, $most, $link, &$boxes): string {
                [, $code, $name, $box, $shape] = $path;
                if ($box !== '') {
                    $boxes[$code] = $box;
                }
                $count = $visitors[$code] ?? 0;
                if ($count === 0) {
                    return '<path class="map-quiet" ' . $shape . '/>';
                }

                // Five steps by share of the busiest country, so one large country cannot
                // leave every other one at the same shade.
                $step = min(self::STEPS, max(1, (int) ceil($count / $most * self::STEPS)));
                $said = $name . ' · ' . t('stats.map_visitors', ['count' => number_format($count)]);

                return '<a href="' . e($link($code)) . '" aria-label="' . e($said) . '">'
                    . '<path class="map-step-' . $step . '" ' . $shape . '><title>' . e($said) . '</title></path></a>';
            },
            $svg,
        );

        $view = self::view($svg, $boxes[$zoom] ?? '');

        // CITIES ONLY WHERE THEY CAN BE TOLD APART. On the whole world, five Croatian cities
        // are five circles inside each other — measured, and it looked like a spill of milk
        // over the Balkans. The shading answers "which countries" there; "where inside one"
        // is a question the country view can answer and the world view cannot.
        return $cities === [] || $view['scale'] >= 1.0 ? $view['svg'] : self::marked($view, $cities);
    }

    /**
     * The map cut to one country's box, or left as it is, and what that did to its scale —
     * which the markers need, so that a dot on a country is the same size on the screen as
     * a dot on the world.
     *
     * @return array{svg: string, scale: float, left: float, top: float, width: float, height: float, top_lat: float, bottom_lat: float}
     */
    private static function view(string $svg, string $box): array
    {
        preg_match('~viewBox="([\d.\- ]+)"~', $svg, $whole);
        preg_match('~data-top-lat="([\d.\-]+)"~', $svg, $top);
        preg_match('~data-bottom-lat="([\d.\-]+)"~', $svg, $bottom);
        $world = array_map('floatval', preg_split('~\s+~', trim($whole[1] ?? '0 0 1000 389')) ?: []);

        $cut = $box === '' ? $world : array_map('floatval', preg_split('~\s+~', trim($box)) ?: []);
        if ($box !== '' && count($cut) === 4) {
            $cut = self::framed($cut, $world);
            $svg = (string) preg_replace('~viewBox="[\d.\- ]+"~', 'viewBox="' . implode(' ', array_map(
                static fn (float $n): string => (string) round($n, 1),
                $cut,
            )) . '"', $svg, 1);
        }

        return [
            'svg' => $svg,
            'scale' => ($cut[2] ?? 1.0) / max(1.0, $world[2] ?? 1.0),
            'left' => $cut[0] ?? 0.0,
            'top' => $cut[1] ?? 0.0,
            'width' => $world[2] ?? 1000.0,
            'height' => $world[3] ?? 389.0,
            'top_lat' => (float) ($top[1] ?? 84),
            'bottom_lat' => (float) ($bottom[1] ?? -56),
        ];
    }

    /**
     * A country's box as the map is actually shown: the same middle, the world's own shape,
     * never closer in than MOST_ZOOM, and never past the world's edge.
     *
     * The shape matters as much as the magnification. A box the shape of Croatia in a panel
     * the shape of a letterbox leaves half the panel empty; taking the world's ratio fills
     * it with the countries around, which is where the neighbouring visitors are anyway.
     *
     * @param list<float> $cut
     * @param list<float> $world
     * @return list<float>
     */
    private static function framed(array $cut, array $world): array
    {
        [$worldWidth, $worldHeight] = [$world[2] ?? 1000.0, $world[3] ?? 389.0];
        $middleX = $cut[0] + $cut[2] / 2;
        $middleY = $cut[1] + $cut[3] / 2;

        $width = max($cut[2] * 1.16, $cut[3] * 1.16 * $worldWidth / $worldHeight, $worldWidth / self::MOST_ZOOM);
        $width = min($width, $worldWidth);
        $height = $width * $worldHeight / $worldWidth;

        // Slid back inside the world rather than clipped, so the country stays whole.
        $left = min(max($middleX - $width / 2, $world[0] ?? 0.0), ($world[0] ?? 0.0) + $worldWidth - $width);
        $top = min(max($middleY - $height / 2, $world[1] ?? 0.0), ($world[1] ?? 0.0) + $worldHeight - $height);

        return [$left, $top, $width, $height];
    }

    /**
     * The cities as circles over the map, biggest where most came from.
     *
     * The projection is the one the map was built with — longitude and latitude straight
     * onto x and y — and its two figures are written into the file, so there is one place
     * that decides them rather than two that have to agree.
     *
     * @param array{svg: string, scale: float, left: float, top: float, width: float, height: float, top_lat: float, bottom_lat: float} $view
     * @param list<array{city: string, country: string, visitors: int, latitude: float, longitude: float}> $cities
     */
    private static function marked(array $view, array $cities): string
    {
        $most = max([1, ...array_column($cities, 'visitors')]);
        $marks = '';
        foreach ($cities as $city) {
            $x = ($city['longitude'] + 180.0) / 360.0 * $view['width'];
            $y = ($view['top_lat'] - $city['latitude']) / 360.0 * $view['width'];
            if ($x < $view['left'] || $y < $view['top']) {
                continue;
            }
            // Between the smallest dot worth drawing and one that still fits a small
            // country, by share of the busiest city, and multiplied by what the zoom did to
            // the scale so a dot is the same size on the screen either way.
            $size = (self::MARK_SMALL + ($city['visitors'] / $most) * (self::MARK_LARGE - self::MARK_SMALL)) * $view['scale'];
            $said = $city['city'] . ' · ' . t('stats.map_visitors', ['count' => number_format($city['visitors'])]);
            $marks .= '<circle class="map-city" cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="' . round($size, 2) . '">'
                . '<title>' . e($said) . '</title></circle>';
        }

        return $marks === ''
            ? $view['svg']
            : (string) preg_replace('~</svg>~', '<g class="map-cities">' . $marks . '</g></svg>', $view['svg'], 1);
    }

    /** Where the file is on this install. */
    public static function path(string $publicPath): string
    {
        return rtrim($publicPath, '/') . '/' . self::FILE;
    }

    /** Whether the map can be drawn at all: the file ships with Boxlet, so normally yes. */
    public static function exists(string $publicPath): bool
    {
        return is_file(self::path($publicPath));
    }

    /** The address of the map file, for anything that wants it as a picture. */
    public static function url(): string
    {
        return Url::asset(self::FILE);
    }
}
