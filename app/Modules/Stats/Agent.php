<?php

namespace App\Modules\Stats;

/**
 * The device, browser and system a User-Agent names, as families without versions
 * (PLAN.md D-051): "Chrome", never "Chrome 128.0.6613.84". A version is detail nobody
 * reads on a small site's statistics, and a family is less of a fingerprint.
 *
 * The order of the checks is the whole of the parser, because User-Agents borrow each
 * other's names: Edge says Chrome and Safari, Chrome says Safari, and everything says
 * Mozilla. Each family is tested before the ones it imitates.
 *
 * An iPad on iPadOS 13 or later sends a Mac's User-Agent on purpose and is counted as a
 * Mac; nothing in the string tells them apart, and only a script could.
 */
final class Agent
{
    /**
     * @return array{device: string, browser: string, os: string} '' where not recognised
     */
    public static function parse(string $userAgent): array
    {
        return [
            'device' => self::device($userAgent),
            'browser' => self::browser($userAgent),
            'os' => self::os($userAgent),
        ];
    }

    private static function device(string $ua): string
    {
        return match (true) {
            preg_match('~iPad|Tablet|PlayBook|Silk/|Kindle|Android(?!.*Mobile)~i', $ua) === 1 => 'tablet',
            preg_match('~Mobi|iPhone|iPod|Windows Phone|BlackBerry|Opera Mini~i', $ua) === 1 => 'mobile',
            default => 'desktop',
        };
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            preg_match('~Edg(e|A|iOS)?/~', $ua) === 1 => 'Edge',
            preg_match('~OPR/|Opera|OPiOS/~', $ua) === 1 => 'Opera',
            preg_match('~SamsungBrowser/~', $ua) === 1 => 'Samsung Internet',
            preg_match('~YaBrowser/~', $ua) === 1 => 'Yandex',
            preg_match('~UCBrowser/~', $ua) === 1 => 'UC Browser',
            preg_match('~Firefox/|FxiOS/~', $ua) === 1 => 'Firefox',
            preg_match('~CriOS/|Chrome/|Chromium/~', $ua) === 1 => 'Chrome',
            preg_match('~Safari/~', $ua) === 1 && preg_match('~Version/|iPhone|iPad|Macintosh~', $ua) === 1 => 'Safari',
            preg_match('~Trident/|MSIE ~', $ua) === 1 => 'Internet Explorer',
            default => '',
        };
    }

    private static function os(string $ua): string
    {
        return match (true) {
            preg_match('~Windows~', $ua) === 1 => 'Windows',
            preg_match('~iPhone|iPad|iPod~', $ua) === 1 => 'iOS',
            preg_match('~Android~', $ua) === 1 => 'Android',
            preg_match('~CrOS~', $ua) === 1 => 'ChromeOS',
            preg_match('~Macintosh|Mac OS X~', $ua) === 1 => 'macOS',
            preg_match('~Linux|X11|Ubuntu|Fedora~', $ua) === 1 => 'Linux',
            default => '',
        };
    }
}
