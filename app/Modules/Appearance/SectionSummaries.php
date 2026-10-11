<?php

namespace App\Modules\Appearance;

use App\Modules\Design\Tokens;

/**
 * THE LINE UNDER EACH SECTION'S NAME on the Appearance screen's home (PLAN.md D-157):
 * "Rounded · 16 px · scale 1.25", built from the values on the screen, never written about
 * them, so it cannot go out of date. And the diagram's legend, which is the same kind of
 * line.
 *
 * Readouts like the ones beside every control (AppearanceForm::readouts): the screen prints
 * them, and /check sends them again while anything moves, under the same names.
 */
final class SectionSummaries
{
    /**
     * @param array<string, string> $decisions the design as drawn, every key answered,
     *        the header and footer included (D-164)
     * @param list<array{passes: bool}> $pairs the contrast pairs, measured
     * @return array<string, string> readout name => what it says
     */
    public static function of(array $decisions, array $pairs): array
    {
        $look = $decisions;
        $readable = Tokens::readable($decisions);
        $boxed = $decisions['boxed'] === 'yes';
        $sheet = $readable['sheet_width'];
        $content = min($readable['container'], $boxed ? $sheet : PHP_INT_MAX);
        $chrome = static fn (string $choice): string => t('chrome.look.' . $choice . '.' . ($look[$choice] ?? ''));

        return [
            'summary.colours' => t('inspector.summary.colours', [
                'seed' => $decisions['seed'],
                'passing' => count(array_filter($pairs, static fn (array $pair): bool => $pair['passes'])),
                'total' => count($pairs),
            ]),
            'summary.typography' => t('inspector.summary.typography', [
                // The two families by name, one when they are the same (D-185).
                'fonts' => implode(' / ', array_unique([\App\Modules\Design\Fonts::ALL[\App\Modules\Design\Fonts::known($decisions['heading_font'])]['name'], \App\Modules\Design\Fonts::ALL[\App\Modules\Design\Fonts::known($decisions['body_font'])]['name']])),
                'size' => $readable['text']['base'],
                'scale' => $decisions['scale'],
            ]),
            'summary.space' => t('inspector.summary.space', [
                'space' => $readable['space'],
                'radius' => $readable['radius'],
                'shadow' => t('design.shadow.' . $decisions['shadow']),
            ]),
            'summary.buttons' => t('inspector.summary.buttons', [
                'style' => t('design.button_style.' . $decisions['button_style']),
                'corners' => (float) $decisions['button_radius'] >= 28 ? t('design.button_radius.pill') : $decisions['button_radius'] . ' px',
            ]),
            'summary.layout' => $boxed
                ? t('inspector.summary.layout_boxed', ['sheet' => $sheet, 'content' => $content])
                : t('inspector.summary.layout_full', ['content' => $content]),
            'summary.header' => implode(' · ', [$chrome('header_arrangement'), $chrome('header_behaviour'), $chrome('header_surface')]),
            'summary.footer' => implode(' · ', [$chrome('footer_layout'), $chrome('footer_surface')]),
            // The diagram's legend: what the outer box is, and how wide the text runs.
            'layout.sheet' => $boxed ? t('inspector.layout.sheet', ['sheet' => $sheet]) : t('inspector.layout.full_width'),
            'layout.content' => t('inspector.layout.content', ['content' => $content]),
        ];
    }
}
