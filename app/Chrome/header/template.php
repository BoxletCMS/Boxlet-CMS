<?php
/**
 * The site header (PLAN.md D-028, D-030, D-112). Class names only; every colour, size and
 * font comes from CSS custom properties in public/assets/chrome.css (SPEC §5.3).
 *
 * @var array<string, mixed> $content logo, logo_dark and button, from the chrome screen
 * @var array<string, mixed> $style   section style layers, as for any block
 * @var string $layout the header's arrangement: left, inline, centred, split, masthead
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media id => resolved picture
 * @var bool $eager
 * @var array<string, mixed> $resolved values the renderer resolved: the menu, each entry
 *                                   marked when it is the page being drawn, the look
 *                                   (ChromeLook), whether the bar carries a colour of its
 *                                   own, the site's name, and whether the ink on the bar is
 *                                   light — which is what chooses the dark-surface logo
 */

/* The menu arrives resolved — label, url and one level of children — or empty when the
   chrome names a menu that does not exist any more. MenuTree::forVisitors() already
   returns [] in that case, so a deleted menu draws no nav rather than broken markup. */
$menu = is_array($resolved['menu'] ?? null) ? $resolved['menu'] : [];

/* The look (PLAN.md D-032, D-036, D-112): closed sets resolved by ChromeLook, so each is a
   class and nothing else. Surface and arrangement arrive as the section's own layers. */
$look = is_array($resolved['look'] ?? null) ? $resolved['look'] : [];
$behaviour = is_string($look['header_behaviour'] ?? null) ? $look['header_behaviour'] : 'static';
$brand = is_string($look['brand'] ?? null) ? $look['brand'] : 'logo';

/* WHICH LOGO (D-112): the one for dark surfaces when the renderer says the ink on this bar
   is light and the owner has set one; else the site's. The renderer decides, because it
   knows the palette and the section under a header laid over the page; the template only
   draws what it is handed. */
$onDark = ($resolved['logo_dark'] ?? false) === true;
$logoId = $onDark && is_int($content['logo_dark'] ?? null) && isset($media[$content['logo_dark']])
    ? $content['logo_dark']
    : (is_int($content['logo'] ?? null) ? $content['logo'] : null);
/* `natural` and `full`, the presets that are never cropped (SPEC §5.5): a logo keeps the
   shape it was uploaded in (D-038). thumb and card cut a wide logo down to its middle.
   `natural` first since D-119, so a logo 20em wide is not sent at up to 2400 px. */
$logoTag = \App\Modules\Media\MediaPicture::tag($logoId === null ? null : ($media[$logoId] ?? null), ['natural', 'full'], '20em', true);
$button = $content['button'];

/* A colour of the owner's own (D-076) is a CLASS on the bar, and the class is what lets
   chrome.css set the section's tokens from the --chrome-header-* ones with no fallback —
   a fallback naming the token itself is a cycle, and a cycle is a token that quietly
   becomes nothing (D-110). Never on a header laid over the first section: that behaviour
   exists to paint nothing and take the colours beneath it. */
$ownColour = ($resolved['own'] ?? false) === true && $behaviour !== 'over';
$classes = 'site-header density-' . ($look['density'] ?? 'normal')
    . ' logo-' . ($look['logo_size'] ?? 'medium')
    . ' behaviour-' . $behaviour
    . ' edge-' . ($look['header_edge'] ?? 'none')
    . ' nav-' . ($look['nav_style'] ?? 'plain')
    . ' nav-ink-' . ($look['nav_ink'] ?? 'accent')
    . ' button-' . ($look['header_button'] ?? 'filled')
    . ' brand-' . $brand
    . ($ownColour ? ' own-colour' : '');

/* NOT t(), for the reason the language switcher gives: site_t() says the site's own few
   words in the page's language (lang/{code}/site.php, D-044). */
$menuLabel = site_t('site.menu', (string) ($locale ?? ''));
/* THE SITE'S NAME STANDS IN FOR A LOGO IT DOES NOT HAVE (D-110), or beside it, or instead
   of it, as the brand choice says (D-112). It is set in the heading face by chrome.css, so
   it takes the character like everything else. */
$siteName = is_string($resolved['site_name'] ?? null) ? trim($resolved['site_name']) : '';
$showLogo = $brand !== 'name' && $logoTag !== '';
$showName = $siteName !== '' && ($brand !== 'logo' || $logoTag === '');
$home = \App\Support\Url::page($locale ?? '');

/* SPLIT: the name in the middle of its menu (D-112), which is two lists around it. The
   first half takes the odd item. One nav, so the phone's one Menu button folds both. */
$lists = [$menu];
if ($layout === 'split' && count($menu) > 1) {
    $half = intdiv(count($menu) + 1, 2);
    $lists = [array_slice($menu, 0, $half), array_slice($menu, $half)];
}
$at = 0;
?>
<div class="<?= e($classes) ?>" data-site-header>
<?php if ($showLogo && $showName): ?>
    <a class="site-logo site-brand" href="<?= e($home) ?>"><?= $logoTag ?><span class="site-name"><?= e($siteName) ?></span></a>
<?php elseif ($showLogo): ?>
    <a class="site-logo" href="<?= e($home) ?>"><?= $logoTag ?></a>
<?php elseif ($showName): ?>
    <a class="site-logo site-name" href="<?= e($home) ?>"><?= e($siteName) ?></a>
<?php endif; ?>

<?php if ($menu !== []): ?>
<?php /* The toggle is for narrow screens and exists only once site-nav.js has run: it is
         born hidden, so without a script there is no button that does nothing, and the
         navigation simply wraps under the logo. */ ?>
<?php /* A hamburger, drawn (D-114): three lines in the bar's own ink, with the word for a
         screen reader. Drawn, not written, for the reason the link icon gives: an emoji is a
         box wherever its font is missing, and the word took a whole line beside a logo. */ ?>
    <button type="button" class="site-nav-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="<?= e($menuLabel) ?>" hidden data-site-nav-toggle><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    <nav class="site-nav" id="site-nav" aria-label="<?= e($menuLabel) ?>">
<?php foreach ($lists as $items): ?>
        <ul>
<?php foreach ($items as $item): ?>
<?php $i = $at++; ?>
            <li<?= !empty($item['current_parent']) ? ' class="is-current-parent"' : '' ?>>
                <a href="<?= e($item['url']) ?>"<?= !empty($item['current']) ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
<?php if ($item['children'] !== []): ?>
<?php /* A submenu opens by its own button, never by hover alone: a menu that appears only
         under a pointer is invisible to a keyboard and to a finger. Without a script the
         button stays hidden and the submenu is simply listed under its parent. */ ?>
                <button type="button" class="site-nav-more" aria-expanded="false" aria-controls="site-nav-<?= e((string) $i) ?>" aria-label="<?= e($item['label']) ?>" hidden data-site-nav-more><span aria-hidden="true"><svg viewBox="0 0 12 12" width="10" height="10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M2.5 4.5 6 8l3.5-3.5"/></svg></span></button>
                <ul class="site-nav-children" id="site-nav-<?= e((string) $i) ?>">
<?php foreach ($item['children'] as $child): ?>
                    <li><a href="<?= e($child['url']) ?>"<?= !empty($child['current']) ? ' aria-current="page"' : '' ?>><?= e($child['label']) ?></a></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
<?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php /* Optional by design (D-028): a header with no call to action draws none, rather
         than an empty button shape. Both halves are required — a label with no address is
         not a link, and an address with no label is nothing to click. Filled, outlined or a
         plain link, as the look says (D-112). */ ?>
<?php if ($button['url'] !== '' && $button['label'] !== ''): ?>
    <p class="site-header-action"><a class="<?= e(($look['header_button'] ?? 'filled') === 'text' ? 'site-header-link' : 'button' . (($look['header_button'] ?? 'filled') === 'outline' ? ' button-outline' : '')) ?>" href="<?= e($button['url']) ?>"><?= e($button['label']) ?></a></p>
<?php endif; ?>
</div>
