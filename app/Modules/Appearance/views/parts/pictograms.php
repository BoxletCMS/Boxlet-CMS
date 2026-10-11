<?php

/**
 * HOW A HEADER OR A FOOTER IS ARRANGED, DRAWN (PLAN.md D-161, the mockup's tiles): a strip,
 * a mark for the logo, lines for the menu and the words, placed as chrome-header.css and
 * chrome.css place them. Required by parts/controls.php, in its scope.
 *
 * SVG built from attributes only — the admin's CSP refuses a style attribute — and drawn in
 * the admin's colours by class (admin-controls.css): this is a picture of an arrangement,
 * not of the site's design.
 */

/**
 * The drawing for one answer, or '' for a choice that has none. The header's and the
 * footer's arrangements are their blocks' layouts, so the drawings are the ones their
 * block.php declares (D-166): one drawing per arrangement, wherever it is shown.
 *
 * @var \App\Core\Blocks $chromeBlocks
 */
$pictogram = static function (string $choice, string $value) use ($chromeBlocks): string {
    $type = ['header_arrangement' => 'header', 'footer_layout' => 'footer'][$choice] ?? null;
    $parts = $type === null ? null : ($chromeBlocks->get($type)['pictograms'][$value] ?? null);

    return $parts === null ? '' : \App\Support\Pictogram::svg($parts);
};
