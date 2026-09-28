<?php

namespace App\Support;

use App\Core\Request;

/**
 * The visitor's own address where the site sits behind something else (PLAN.md O-20).
 *
 * Behind Cloudflare, a load balancer or a caching proxy, every request arrives from that
 * machine: statistics would see one visitor, the form's rate limit would count everyone
 * together, and the login limit would lock the owner out because of somebody else. The real
 * address is in X-Forwarded-For — which any visitor can also send, so it is read ONLY when
 * the request came from an address the owner has listed as their proxy.
 *
 * The list is addresses or ranges, one per line: 173.245.48.0/20, 2400:cb00::/32, 10.0.0.7.
 * Empty, which is the default, means the address is taken as the server reports it.
 */
final class ClientIp
{
    public static function of(Request $request, string $trusted): string
    {
        $proxies = self::parse($trusted);
        if ($proxies === [] || !self::isTrusted($request->ip, $proxies)) {
            return $request->ip;
        }

        // Right to left: each proxy appends the address it saw, so the last address that is
        // not one of ours is the first one we cannot vouch for — the visitor, or the last
        // machine before our proxies.
        $forwarded = array_map('trim', explode(',', (string) $request->header('x-forwarded-for')));
        foreach (array_reverse($forwarded) as $candidate) {
            $address = self::address($candidate);
            if ($address !== '' && !self::isTrusted($address, $proxies)) {
                return $address;
            }
        }

        return $request->ip;
    }

    /**
     * @param list<array{string, int}> $proxies
     */
    private static function isTrusted(string $ip, array $proxies): bool
    {
        $packed = self::packed($ip);
        if ($packed === null) {
            return false;
        }
        foreach ($proxies as [$network, $bits]) {
            $range = self::packed($network);
            if ($range === null || strlen($range) !== strlen($packed)) {
                continue;
            }
            if (self::sameNetwork($packed, $range, $bits)) {
                return true;
            }
        }

        return false;
    }

    /** Whether two packed addresses agree on their first $bits bits. */
    private static function sameNetwork(string $a, string $b, int $bits): bool
    {
        $bits = min($bits, strlen($a) * 8);
        $whole = intdiv($bits, 8);
        if ($whole > 0 && substr($a, 0, $whole) !== substr($b, 0, $whole)) {
            return false;
        }
        $left = $bits % 8;
        if ($left === 0) {
            return true;
        }
        $mask = 0xFF << (8 - $left) & 0xFF;

        return (ord($a[$whole]) & $mask) === (ord($b[$whole]) & $mask);
    }

    /**
     * The list as addresses and how many bits of each count. A plain address counts whole.
     *
     * @return list<array{string, int}>
     */
    private static function parse(string $trusted): array
    {
        $proxies = [];
        foreach (preg_split('~[\s,]+~', trim($trusted)) ?: [] as $entry) {
            if ($entry === '') {
                continue;
            }
            $slash = strpos($entry, '/');
            $network = $slash === false ? $entry : substr($entry, 0, $slash);
            $bits = $slash === false ? '' : substr($entry, $slash + 1);
            $packed = self::packed($network);
            if ($packed === null) {
                continue;
            }
            $proxies[] = [$network, ctype_digit($bits) ? (int) $bits : strlen($packed) * 8];
        }

        return $proxies;
    }

    /** One entry of X-Forwarded-For: an address, with a port dropped where one is attached. */
    private static function address(string $value): string
    {
        $value = trim($value, " \t[]");
        if (str_contains($value, ']:')) {
            $value = substr($value, 0, (int) strpos($value, ']:'));
        } elseif (substr_count($value, ':') === 1 && str_contains($value, '.')) {
            $value = substr($value, 0, (int) strpos($value, ':'));
        }

        $value = trim($value, '[]');

        return self::packed($value) === null ? '' : $value;
    }

    /**
     * An address as bytes, or null when it is not an address. Asked of filter_var first,
     * because inet_pton warns on anything that is not one — and an error handler that
     * ignores @, which the test runner is, turns that warning into a failure.
     */
    private static function packed(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $packed = inet_pton($ip);

        return $packed === false ? null : $packed;
    }
}
