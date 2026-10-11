<?php

namespace App\Modules\Design;

/**
 * A NUMBER AS CSS WRITES IT, THE SAME ON EVERY PHP (PLAN.md O-40, D-164).
 *
 * The design layer wrote numbers with number_format(), which rounds through PHP's round();
 * PHP 8.4 dropped round()'s pre-rounding, so one size in Bold came out 5.467rem on 8.4 and
 * 5.468rem on 8.1–8.3, and a host moving a site between them got another stylesheet. Here
 * the rounding is integer arithmetic on the value's own digits: scaled, half-up, by floor()
 * of an exact IEEE operation, which no PHP version has ever done differently.
 *
 * Everything that becomes CSS goes through of(): Derived, the readouts, the controls.
 */
final class CssNumber
{
    /**
     * The shortest text that says $value to $decimals places: 2.0 as "2", 0.125 as "0.125",
     * -0.03 as "-0.03". Never "-0".
     */
    public static function of(float $value, int $decimals = 3): string
    {
        $scale = 10 ** $decimals;
        $units = (int) floor(abs($value) * $scale + 0.5);
        if ($units === 0) {
            return '0';
        }
        $whole = intdiv($units, $scale);
        $fraction = rtrim(str_pad((string) ($units % $scale), $decimals, '0', STR_PAD_LEFT), '0');

        return ($value < 0 ? '-' : '') . $whole . ($fraction !== '' ? '.' . $fraction : '');
    }

    /** A length in rem from pixels at the 16px root the whole model assumes. */
    public static function rem(float $px): string
    {
        return self::of($px / 16) . 'rem';
    }
}
