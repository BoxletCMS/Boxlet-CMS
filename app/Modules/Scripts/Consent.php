<?php

namespace App\Modules\Scripts;

use App\Core\Db;

/**
 * WHAT A VISITOR'S PAGE CARRIES OF THE OWNER'S SCRIPTS, AND THE BOX THAT ASKS (PLAN.md O-73).
 *
 * A necessary script is written into the page as it is. A statistics or marketing one is written
 * inside a <template>, which the browser parses and never runs: no script starts, no picture or
 * frame loads, nothing is set. site-consent.js puts a template's content into the page once the
 * visitor has said yes to its kind, and remembers the answer in their own browser.
 *
 * So the page is the same for every visitor, and the page cache keeps working (D-053): what one
 * visitor agreed to is never the server's to know.
 *
 * The box is drawn only where a script waits for consent: a site with none asks nothing, which is
 * Boxlet's promise kept — no banner until the owner chooses scripts that need one.
 */
final class Consent
{
    /** The visitor's answer, in their browser: the one thing this stores, and it is necessary. */
    public const STORAGE_KEY = 'boxlet-consent';

    /**
     * @return array{head: string, body: string, box: string}
     */
    public static function forPage(Db $db, string $locale): array
    {
        $scripts = Scripts::enabled($db);
        $parts = ['head' => '', 'body' => ''];
        $asked = [];
        foreach ($scripts as $script) {
            $waits = in_array($script['kind'], Scripts::CONSENTED, true);
            if ($waits) {
                $asked[$script['kind']] = true;
            }
            // The owner's code as they wrote it: admin-only, and the point of the feature.
            $parts[$script['place']] .= ($waits
                ? '<template data-consent-kind="' . e($script['kind']) . '">' . $script['code'] . '</template>'
                : $script['code']) . "\n";
        }

        return ['head' => $parts['head'], 'body' => $parts['body'], 'box' => $asked === [] ? '' : self::box($scripts, array_keys($asked), $locale)];
    }

    /**
     * The box, drawn in the page and shown by site-consent.js when the visitor has not answered.
     *
     * @param list<array{kind: string, cookies: list<array{name: string, provider: string, purpose: string, duration: string}>}> $scripts
     * @param list<string> $asked the kinds that wait for a yes, in the order they were met
     */
    private static function box(array $scripts, array $asked, string $locale): string
    {
        $kinds = [];
        foreach (Scripts::KINDS as $kind) {
            if ($kind !== 'necessary' && !in_array($kind, $asked, true)) {
                continue;
            }
            $cookies = [];
            foreach ($scripts as $script) {
                if ($script['kind'] === $kind) {
                    array_push($cookies, ...$script['cookies']);
                }
            }
            if ($kind === 'necessary') {
                // What this box keeps is necessary too, and named: the visitor's answer.
                $cookies[] = ['name' => self::STORAGE_KEY, 'provider' => site_t('site.consent.this_site', $locale), 'purpose' => site_t('site.consent.remembers', $locale), 'duration' => site_t('site.consent.until_cleared', $locale)];
            }
            $kinds[$kind] = $cookies;
        }

        ob_start();
        require __DIR__ . '/views/box.php';

        return (string) ob_get_clean();
    }
}
