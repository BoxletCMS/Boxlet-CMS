<?php

namespace App\Modules\Stats;

/**
 * The suggested privacy-policy text for visit statistics (PLAN.md D-051), in every language
 * that has one (lang/{code}/stats-privacy.php), written for this site's settings: the
 * retention period it names is the one set, the paragraph on location says as much as the
 * site actually counts, and the one on Do Not Track appears only when it is honoured.
 *
 * A suggestion to adapt, not legal advice, and the panel says so.
 */
final class PrivacyText
{
    /**
     * @param array{enabled: bool, dnt: bool, retention: int, location?: string, cityMin?: int} $settings
     * @return array<string, array{language: string, text: string}> by language code
     */
    public static function all(array $settings, bool $countries): array
    {
        $texts = [];
        foreach (glob(dirname(__DIR__, 3) . '/lang/*/stats-privacy.php') ?: [] as $file) {
            $strings = require $file;
            if (!is_array($strings)) {
                continue;
            }
            $say = static fn (string $key): string => is_string($strings[$key] ?? null) ? $strings[$key] : '';
            $paragraphs = [
                $say('privacy.heading'),
                $say('privacy.what'),
                $say('privacy.visitor'),
                // The paragraph about location says as much as the site actually counts
                // (D-055): a text claiming only the country while the city is being counted
                // would be the worst kind of wrong.
                $countries ? $say('privacy.' . match ($settings['location'] ?? 'country') {
                    'city' => 'city',
                    'region' => 'region',
                    default => 'country',
                }) : '',
                /* AND THE PROMISE ABOUT SMALL PLACES, ONLY WHILE IT IS TRUE (D-109). The
                   owner may lower the floor under the cities to one, and then every city is
                   named however few visitors it had — so the sentence saying a total cannot
                   point at one person is withheld rather than left standing as a lie. Same
                   rule as the paragraph above: the text says what the site actually counts. */
                $countries && ($settings['location'] ?? 'country') === 'city' && ($settings['cityMin'] ?? PlaceQuery::DEFAULT_MIN) > 1
                    ? $say('privacy.city_floor')
                    : '',
                str_replace(':period', $say('privacy.period.' . $settings['retention']), $say('privacy.kept')),
                $settings['dnt'] ? $say('privacy.dnt') : '',
                $say('privacy.basis'),
            ];
            $texts[basename(dirname($file))] = [
                'language' => $say('privacy.language'),
                'text' => implode("\n\n", array_filter($paragraphs, static fn (string $p): bool => $p !== '')),
            ];
        }
        // English first, as the one every site can fall back on; the rest by code.
        uksort($texts, static fn (string $a, string $b): int => [$a !== 'en', $a] <=> [$b !== 'en', $b]);

        return $texts;
    }
}
