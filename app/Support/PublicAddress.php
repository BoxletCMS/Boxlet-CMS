<?php

namespace App\Support;

use Closure;
use RuntimeException;

/**
 * AN ADDRESS THE SERVER MAY CALL ON SOMEONE'S SAY-SO (PLAN.md O-77, O-78): a form's web address,
 * a picture an AI client asks the site to fetch. Only https, and only the public internet: a
 * request made because someone typed an address must not make the server knock on its own
 * neighbours (127.0.0.1, the host's private network, a cloud's metadata address).
 *
 * The name is resolved once and every address it gives must be public; the request is then
 * pinned to the first (CURLOPT_RESOLVE), so a name cannot answer differently the second time.
 * PHP's private and reserved filters leave 100.64.0.0/10 (shared address space) open; that is
 * noted in PLAN.md D-284 rather than guessed at.
 */
final class PublicAddress
{
    /** '' when $url may be called, checked without asking the network; else why not, in words. */
    public static function invalid(string $url): string
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $host === ''
            || isset($parts['user']) || isset($parts['pass']) || mb_strlen($url) > 2000) {
            return t('address.https');
        }
        $ip = trim($host, '[]');
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')
            || (filter_var($ip, FILTER_VALIDATE_IP) !== false && !self::public($ip))) {
            return t('address.private');
        }

        return '';
    }

    /**
     * The address to call: its host and port, and the one public IP the request is pinned to.
     * Throws, in words, for an address that may not be called or a name that resolves to
     * nothing public.
     *
     * @param (Closure(string): list<string>)|null $resolve a host's addresses; DNS by default
     * @return array{host: string, port: int, pin: string} pin is CURLOPT_RESOLVE's entry
     */
    public static function check(string $url, ?Closure $resolve = null): array
    {
        $invalid = self::invalid($url);
        if ($invalid !== '') {
            throw new RuntimeException($invalid);
        }
        $host = trim(strtolower((string) parse_url($url, PHP_URL_HOST)), '[]');
        $port = (int) (parse_url($url, PHP_URL_PORT) ?? 443);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($resolve ?? self::resolve(...))($host);
        if ($addresses === []) {
            throw new RuntimeException(t('address.unknown_host', ['host' => $host]));
        }
        foreach ($addresses as $address) {
            if (!self::public($address)) {
                throw new RuntimeException(t('address.private'));
            }
        }

        return ['host' => $host, 'port' => $port, 'pin' => $host . ':' . $port . ':' . (str_contains($addresses[0], ':') ? '[' . $addresses[0] . ']' : $addresses[0])];
    }

    /** An address on the public internet: no private, loopback, link-local or reserved range. */
    public static function public(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
