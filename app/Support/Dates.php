<?php

namespace App\Support;

use App\Core\Db;
use App\Core\Settings;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * A stored time as the owner reads it (SPEC §5.0: times are stored as UTC strings).
 *
 * The site's time zone is a setting, so a date on an admin screen is shown in it: an owner
 * in Zagreb who edited a page at nine should not read seven. Two callers today, the page
 * list and a picture's details.
 */
final class Dates
{
    /** The site's time zone, UTC when none is set or the one set is unknown here. */
    public static function zone(Db $db): string
    {
        $zone = Settings::text($db, 'timezone', 'UTC');

        return in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC';
    }

    /**
     * "18 Sep 2026, 09:40" in $zone, from a stored Y-m-d H:i:s UTC string. A value that is
     * not one comes back as it is: a date shown raw is better than a date made up.
     */
    public static function local(string $utc, string $zone): string
    {
        return self::format($utc, $zone, 'j M Y, H:i');
    }

    /**
     * The same, to the second: "18 Sep 2026, 09:40:12".
     *
     * For a list where several rows can share a minute and the reader has to tell them
     * apart — a page's earlier versions (D-088), where five saves in one working session
     * all read "14:15" and the list said nothing about which was which.
     */
    public static function localToSecond(string $utc, string $zone): string
    {
        return self::format($utc, $zone, 'j M Y, H:i:s');
    }

    private static function format(string $utc, string $zone, string $pattern): string
    {
        $time = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
        if ($time === false) {
            return $utc;
        }
        try {
            $time = $time->setTimezone(new DateTimeZone($zone));
        } catch (Throwable) {
            // An unknown zone reads as UTC rather than as an error on a list screen.
        }

        return $time->format($pattern);
    }
}
