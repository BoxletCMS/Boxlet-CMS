<?php

declare(strict_types=1);

namespace App\Support;

/**
 * WHAT AN EMBED BLOCK IS ALLOWED TO PUT IN AN IFRAME (PLAN.md D-105).
 *
 * THE RULE: the pasted address is never the iframe's src. It is parsed into a provider and
 * an id, and the src is BUILT from that id against a fixed template. An address that does
 * not parse produces nothing at all. So whatever ends up stored — a typo, an old paste, a
 * string somebody put there through a database they had access to — the only thing that can
 * ever be framed is one of the four addresses written below, with an id matching its own
 * pattern.
 *
 * That is why there is no free HTML field and no "paste the embed code" box, which is the
 * shape every other small CMS uses and the reason they all ship an XSS. The whitelist in
 * RichText.php already refuses <iframe>; this is the one place in Boxlet that writes one.
 *
 * THE FOUR: YouTube and Vimeo because that is what a video is; OpenStreetMap because a map
 * should not cost the visitor a tracker; Google Maps because it is what people actually
 * have in the clipboard. A fifth needs its own decision, and its own line here.
 */
final class Embed
{
    /** How wide a map's box is at each zoom level, in degrees of longitude. */
    private const MAP_SPAN = 360.0;

    /**
     * The provider and the address to frame, or null when nothing here recognises it.
     *
     * @return array{provider: string, src: string}|null
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            return null;
        }
        // Parsed rather than pattern-matched over the whole string: a host is a host, and
        // "youtube.com" appearing in a path or a query of some other site is not one.
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }
        /*
         * A SCHEME IS REQUIRED, AND IT IS http OR https.
         *
         * Not because a scheme-less "//youtube.com/embed/…" would be dangerous — the src is
         * rebuilt either way, so it could not be — but because a paste that came out of a
         * browser's address bar always has one, and anything without one came from somewhere
         * else. Refusing it costs an owner nothing and is a rule that can be stated in a
         * sentence, which is worth more here than being accommodating.
         */
        $scheme = strtolower(is_string($parts['scheme'] ?? null) ? $parts['scheme'] : '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        $host = strtolower($parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = [];
        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        return self::youtube($host, $path, $query)
            ?? self::vimeo($host, $path)
            ?? self::openStreetMap($host, $path, isset($parts['fragment']) ? (string) $parts['fragment'] : '')
            ?? self::googleMaps($host, $path, $query);
    }

    /**
     * youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID, youtube.com/shorts/ID.
     *
     * Framed through youtube-nocookie.com, which is YouTube's own address for an embed that
     * sets no advertising cookie until the visitor presses play.
     *
     * @param array<array-key, array<mixed>|string> $query parse_str()’s own shape: a key may repeat as a list
     * @return array{provider: string, src: string}|null
     */
    private static function youtube(string $host, string $path, array $query): ?array
    {
        $id = null;
        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com' || $host === 'm.youtube.com') {
            if ($path === '/watch') {
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('~^/(?:embed|shorts|v)/([^/?#]+)~', $path, $found) === 1) {
                $id = $found[1];
            }
        }
        // Eleven characters of the URL-safe alphabet, which is what a video id is. Anything
        // else is not one, whatever it came attached to.
        if (!is_string($id) || preg_match('~^[A-Za-z0-9_-]{11}$~', $id) !== 1) {
            return null;
        }

        return ['provider' => 'youtube', 'src' => "https://www.youtube-nocookie.com/embed/{$id}"];
    }

    /**
     * vimeo.com/123456789 and player.vimeo.com/video/123456789.
     *
     * @return array{provider: string, src: string}|null
     */
    private static function vimeo(string $host, string $path): ?array
    {
        if ($host !== 'vimeo.com' && $host !== 'player.vimeo.com') {
            return null;
        }
        if (preg_match('~^(?:/video)?/([0-9]{6,12})(?:/|$)~', $path, $found) !== 1) {
            return null;
        }

        return ['provider' => 'vimeo', 'src' => "https://player.vimeo.com/video/{$found[1]}"];
    }

    /**
     * openstreetmap.org/#map=15/45.8150/15.9819 — the address the site's own URL bar holds.
     *
     * OSM's embed takes a bounding box rather than a centre and a zoom, so the box is
     * computed: at zoom z the whole world is 2^z tiles across, and one screen is a couple of
     * tiles of it. Two tiles' worth is what the map looks like when you copied the address.
     *
     * @return array{provider: string, src: string}|null
     */
    private static function openStreetMap(string $host, string $path, string $fragment): ?array
    {
        if ($host !== 'openstreetmap.org' && $host !== 'openstreetmap.de') {
            return null;
        }
        $where = $fragment !== '' ? $fragment : ltrim($path, '/');
        if (preg_match('~map=([0-9]{1,2})/(-?[0-9]{1,3}(?:\.[0-9]+)?)/(-?[0-9]{1,3}(?:\.[0-9]+)?)~', $where, $found) !== 1) {
            return null;
        }
        [, $zoom, $lat, $lon] = $found;
        $point = self::point((float) $lat, (float) $lon);
        if ($point === null) {
            return null;
        }
        [$lat, $lon] = $point;
        $span = self::MAP_SPAN / (2 ** max(1, min(19, (int) $zoom)));
        $box = implode(',', [
            self::degrees($lon - $span), self::degrees($lat - $span / 2),
            self::degrees($lon + $span), self::degrees($lat + $span / 2),
        ]);

        return ['provider' => 'openstreetmap', 'src' => "https://www.openstreetmap.org/export/embed.html?bbox={$box}&layer=mapnik"];
    }

    /**
     * google.com/maps/@45.8150,15.9819,15z and .../maps/place/Name/@45.8150,15.9819,15z.
     *
     * The classic `maps?q=…&output=embed` form, which is the only Google map that frames
     * without an API key. A place NAME is deliberately not carried over: it would mean
     * putting text somebody pasted into a query string, and the coordinates say where it is.
     *
     * @param array<array-key, array<mixed>|string> $query parse_str()’s own shape: a key may repeat as a list
     * @return array{provider: string, src: string}|null
     */
    private static function googleMaps(string $host, string $path, array $query): ?array
    {
        // google.com, google.hr, google.co.uk, and the same four with a maps. in front:
        // the address bar gives whichever country the visitor's browser asked for.
        $host = str_starts_with($host, 'maps.') ? substr($host, 5) : $host;
        if (preg_match('~^google\.[a-z]{2,3}(?:\.[a-z]{2})?$~', $host) !== 1) {
            return null;
        }
        if (!str_starts_with($path, '/maps') && $path !== '/') {
            return null;
        }
        if (preg_match('~@(-?[0-9]{1,3}(?:\.[0-9]+)?),(-?[0-9]{1,3}(?:\.[0-9]+)?),([0-9]{1,2})(?:\.[0-9]+)?z~', $path, $found) !== 1) {
            // The short form, ?q=lat,lon, which a shared pin gives.
            $q = is_string($query['q'] ?? null) ? $query['q'] : '';
            if (preg_match('~^(-?[0-9]{1,3}(?:\.[0-9]+)?),\s*(-?[0-9]{1,3}(?:\.[0-9]+)?)$~', $q, $found) !== 1) {
                return null;
            }
            $found[3] = '15';
        }
        $point = self::point((float) $found[1], (float) $found[2]);
        if ($point === null) {
            return null;
        }
        [$lat, $lon] = $point;
        $zoom = max(1, min(21, (int) $found[3]));

        return [
            'provider' => 'googlemaps',
            'src' => 'https://maps.google.com/maps?q=' . self::degrees($lat) . ',' . self::degrees($lon)
                . "&z={$zoom}&output=embed",
        ];
    }

    /**
     * A point on the globe, or null if it is not one.
     *
     * @return array{0: float, 1: float}|null
     */
    private static function point(float $lat, float $lon): ?array
    {
        return abs($lat) > 90.0 || abs($lon) > 180.0 ? null : [$lat, $lon];
    }

    /** A coordinate as a plain decimal: no exponent, no locale, six places is a few metres. */
    private static function degrees(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') ?: '0';
    }
}
