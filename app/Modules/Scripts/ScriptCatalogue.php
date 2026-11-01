<?php

namespace App\Modules\Scripts;

/**
 * THE SERVICES BOXLET KNOWS BY THEIR CODE (PLAN.md O-73; the owner: "kako ćemo zapravo znati koje
 * cookie admin servira posjetiteljima?"). A script pasted in is recognised by the addresses and
 * calls its provider's snippet always carries, and given its kind and the cookies the provider
 * documents, so the banner's list is right without the owner looking anything up.
 *
 * What a service sets on ITS OWN domain (facebook.com, doubleclick.net) no script on the page can
 * see, the check in the admin included; only what it sets on the site's own is listed here, as a
 * provider's documentation names it. A name ending in * stands for every name that begins so: the
 * end is the account's (site-consent.js removes them so).
 *
 * The services that set no cookie at all are known too: such a script needs no consent, and the
 * owner is told so.
 *
 * @phpstan-type Service array{name: string, patterns: list<string>, kind: string, cookies: list<array{string, string, string}>, cookieless?: bool, loads?: bool}
 */
final class ScriptCatalogue
{
    /** @var array<string, Service> */
    private const SERVICES = [
        'google-analytics' => ['name' => 'Google Analytics', 'patterns' => ['googletagmanager.com/gtag/js', 'google-analytics.com/analytics.js', "gtag('config'", 'gtag("config"'], 'kind' => 'statistics',
            'cookies' => [['_ga', 'visitor', '2y'], ['_ga_*', 'session', '2y']]],
        // A container that loads whatever the owner put in it: its own cookies are none, the
        // tags' are theirs, which the check finds and the owner names.
        'google-tag-manager' => ['name' => 'Google Tag Manager', 'patterns' => ['googletagmanager.com/gtm.js', 'googletagmanager.com/ns.html'], 'kind' => 'marketing', 'cookies' => [], 'loads' => true],
        'meta-pixel' => ['name' => 'Meta Pixel', 'patterns' => ['connect.facebook.net', 'fbq('], 'kind' => 'marketing', 'cookies' => [['_fbp', 'ads', '90d']]],
        'hotjar' => ['name' => 'Hotjar', 'patterns' => ['static.hotjar.com', 'hotjar.com/c/hotjar-'], 'kind' => 'statistics',
            'cookies' => [['_hjSessionUser_*', 'visitor', '1y'], ['_hjSession_*', 'session', '30min']]],
        'clarity' => ['name' => 'Microsoft Clarity', 'patterns' => ['clarity.ms'], 'kind' => 'statistics', 'cookies' => [['_clck', 'visitor', '1y'], ['_clsk', 'session', '1d']]],
        'matomo' => ['name' => 'Matomo', 'patterns' => ['matomo.js', 'piwik.js', '_paq.push'], 'kind' => 'statistics', 'cookies' => [['_pk_id.*', 'visitor', '13m'], ['_pk_ses.*', 'session', '30min']]],
        'plausible' => ['name' => 'Plausible', 'patterns' => ['plausible.io/js'], 'kind' => 'necessary', 'cookies' => [], 'cookieless' => true],
        'fathom' => ['name' => 'Fathom', 'patterns' => ['usefathom.com'], 'kind' => 'necessary', 'cookies' => [], 'cookieless' => true],
        'umami' => ['name' => 'Umami', 'patterns' => ['umami.is', 'cloud.umami', 'umami.js'], 'kind' => 'necessary', 'cookies' => [], 'cookieless' => true],
        'simple-analytics' => ['name' => 'Simple Analytics', 'patterns' => ['simpleanalyticscdn.com'], 'kind' => 'necessary', 'cookies' => [], 'cookieless' => true],
    ];

    /**
     * The service $code is the snippet of, with its cookies in $locale's words; null for one
     * Boxlet does not know.
     *
     * @return array{key: string, name: string, kind: string, cookieless: bool, loads: bool, cookies: list<array{name: string, provider: string, purpose: string, duration: string}>}|null
     */
    public static function recognise(string $code, string $locale): ?array
    {
        $lower = strtolower($code);
        foreach (self::SERVICES as $key => $service) {
            foreach ($service['patterns'] as $pattern) {
                if (str_contains($lower, strtolower($pattern))) {
                    return [
                        'key' => $key,
                        'name' => $service['name'],
                        'kind' => $service['kind'],
                        'cookieless' => $service['cookieless'] ?? false,
                        'loads' => $service['loads'] ?? false,
                        'cookies' => array_map(static fn (array $c): array => [
                            'name' => $c[0],
                            'provider' => $service['name'],
                            'purpose' => site_t('site.consent.catalogue.' . $c[1], $locale),
                            'duration' => site_t('site.consent.catalogue.' . $c[2], $locale),
                        ], $service['cookies']),
                    ];
                }
            }
        }

        return null;
    }
}
