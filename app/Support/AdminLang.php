<?php

namespace App\Support;

use Closure;
use DateTimeInterface;

/**
 * THE ADMIN'S OWN LANGUAGE (PLAN.md D-240, the owner): the site's main language, the one chosen
 * when it was installed, wherever Boxlet has the admin in it; English everywhere else.
 *
 * A language "has the admin" when lang/{code}/ holds the admin's files beside the visitor's
 * (VISITOR_FILES). A test holds every such language to the whole of English, key for key and
 * placeholder for placeholder, so a language is added complete or not at all; at run time a
 * word it lacks is still English rather than a key on the screen.
 *
 * Not the visitor's words: site_t() reads those in the page's own language.
 */
final class AdminLang
{
    /** The files in lang/{code}/ a visitor's page reads, through site_t() and PrivacyText. */
    public const VISITOR_FILES = ['site.php', 'samples.php', 'stats-privacy.php'];

    /** Set by speaking(): the installer, once its language is chosen, before the site exists. */
    private static ?string $speaking = null;

    /** @var array<string, array<string, string>> every key of a language, English beneath */
    private static array $strings = [];

    /** The code the admin speaks now: `hr`, or `en`. */
    public static function code(): string
    {
        $code = self::$speaking ?? Url::primaryLocale();

        return self::has($code) ? $code : 'en';
    }

    /** Whether lang/{code}/ carries the admin, and not only a visitor's words. */
    public static function has(string $code): bool
    {
        if ($code === 'en') {
            return true;
        }
        if (preg_match('~^[a-z]{2,3}$~', $code) !== 1) {
            return false;
        }
        // shell.php is the admin's frame, every screen's first words; the test makes a language
        // that has it have the rest.
        return is_file(self::dir($code) . '/shell.php');
    }

    /**
     * $then, said in $code: the installer's last steps, in the language just chosen for the
     * site, before the site that would say so exists. Put back afterwards, whatever happens.
     *
     * @template T
     * @param Closure(): T $then
     * @return T
     */
    public static function speaking(string $code, Closure $then): mixed
    {
        $was = self::$speaking;
        self::$speaking = $code;
        try {
            return $then();
        } finally {
            self::$speaking = $was;
        }
    }

    /**
     * Every word of $code's admin: its own, and English for what it lacks.
     *
     * Every file in the directory, not a list of names: the strings were split by concern when
     * one file passed the 300-line rule, and a loader that names its files is edited every time
     * another concern earns one. A key defined in two files is a test failure
     * (tests/lang_test.php), not a silent win for whichever loads first.
     *
     * @return array<string, string>
     */
    public static function strings(string $code): array
    {
        if (!array_key_exists($code, self::$strings)) {
            $own = self::read($code);
            self::$strings[$code] = $code === 'en' ? $own : $own + self::strings('en');
        }

        return self::$strings[$code];
    }

    /**
     * Which form of a counted word $count takes in $code: `one`, `few` or `other`.
     *
     * English, and every language not named here, has one form for 1 and another for the rest.
     * Croatian has three, by the last digits (1, 21, 31 stranica; 2–4, 22–24 stranice; 5–20,
     * 25–30 stranica), and a 21 is "one" there. admin.js repeats this for the counts a script
     * writes; a language with rules of its own is added in both.
     */
    public static function plural(int $count, string $code): string
    {
        $n = abs($count);
        if (in_array($code, ['hr', 'bs', 'sr'], true)) {
            $last = $n % 10;
            $lastTwo = $n % 100;
            if ($last === 1 && $lastTwo !== 11) {
                return 'one';
            }

            return $last >= 2 && $last <= 4 && ($lastTwo < 12 || $lastTwo > 14) ? 'few' : 'other';
        }

        return $n === 1 ? 'one' : 'other';
    }

    /**
     * The key a counted word is under, for $count in the admin's language.
     *
     * $key is the general form (`media.downloads_many`, `builder.structure.sections`); beside
     * it, `…_one` for one and `…_few` for few, where the language has them. A language with no
     * few form, English, is never asked for one.
     */
    public static function countedKey(string $key, int $count): string
    {
        $code = self::code();
        $form = self::plural($count, $code);
        if ($form === 'other') {
            return $key;
        }
        $stem = (string) preg_replace('~_many$~', '', $key);
        $wanted = $stem . '_' . $form;

        return array_key_exists($wanted, self::strings($code)) ? $wanted : $key;
    }

    /** A number as the admin's language writes it: 1,234.5 in English, 1.234,5 in Croatian. */
    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, t('format.decimal'), t('format.thousands'));
    }

    /**
     * A date as the admin's language writes it: "18 Sep 2026" in English, "18. 9. 2026." in
     * Croatian; without the year, "18 Sep" and "18. 9.".
     */
    public static function date(DateTimeInterface $time, bool $withYear = true): string
    {
        return t($withYear ? 'format.date' : 'format.day', [
            'day' => $time->format('j'),
            'month' => t('format.month.' . $time->format('n')),
            'm' => $time->format('n'),
            'year' => $time->format('Y'),
        ]);
    }

    private static function dir(string $code): string
    {
        return dirname(__DIR__, 2) . '/lang/' . $code;
    }

    /** @return array<string, string> */
    private static function read(string $code): array
    {
        $strings = [];
        foreach (glob(self::dir($code) . '/*.php') ?: [] as $file) {
            $part = require $file;
            if (is_array($part)) {
                $strings += array_filter($part, 'is_string');
            }
        }

        return $strings;
    }
}
