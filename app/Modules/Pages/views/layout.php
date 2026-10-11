<?php

use App\Support\Url;

/**
 * Provided by View::render().
 *
 * @var string $locale
 * @var string $title
 * @var string $content rendered HTML of the page template
 * @var string|null $canonical absolute canonical URL; null on error pages
 * @var list<array{hreflang: string, href: string}> $hreflang this page's alternates in other languages
 * @var string $breadcrumbs BreadcrumbList JSON-LD for a page under a parent, '' otherwise (D-129)
 * @var string $description meta description; empty when the page gives none (D-004)
 * @var bool $noindex keep this page out of search engines: the owner's choice, and always
 *                   for a preview (D-170)
 * @var list<string> $fontPreloads the two families' first files, preloaded (D-185)
 * @var string $siteStyles the nineteen visitor stylesheets as one file, '' when it could not be written (D-204)
 * @var bool $designPreview drawn inside the Appearance preview, where the editor-only marks
 *                         show (D-170)
 * @var array{url: string, type: string}|null $icon the site's tab icon (D-028)
 * @var string|null $shareImage absolute URL of the default sharing picture (D-028)
 * @var string $headerHtml the site header, already rendered, or '' when there is none (5c)
 * @var string $footerHtml the site footer, already rendered, or '' when there is none.
 *                         It carries the language switcher: the site has exactly one footer
 *                         and this layout must not draw a second (D-028).
 * @var string $headerBleed 'sheet' to draw the header inside the sheet, 'full' to let it
 *                          reach the window's edge (D-067)
 * @var string $footerBleed the same, for the footer
 */
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
<?php /* Omitted rather than emitted empty: a description repeating the title is worse
         than none, which is why this field does not fall back to it (D-004). */ ?>
<?php if ($description !== ''): ?>
    <meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if ($noindex): ?>
    <meta name="robots" content="noindex">
<?php endif; ?>
<?php if ($canonical !== null): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<?php /* This page in each language it is published in (D-043, step 4); none for a page
         that exists in one language only. */ ?>
<?php foreach ($hreflang as $alternate): ?>
    <link rel="alternate" hreflang="<?= e($alternate['hreflang']) ?>" href="<?= e($alternate['href']) ?>">
<?php endforeach; ?>
<?php /* Where the page sits under its parents, for search engines to show in place of the
         address (D-129). Encoded with JSON_HEX_TAG, so it cannot close this element. */ ?>
<?php if ($breadcrumbs !== ''): ?>
    <script type="application/ld+json"><?= $breadcrumbs ?></script>
<?php endif; ?>
<?php /* One 200×200 file for both: a browser downscales it for the tab, and iOS takes the
         same picture for a home-screen icon. sizes="any" says it is not a fixed 16 or 32
         rather than claiming a size it is not (D-028). */ ?>
<?php if ($icon !== null): ?>
    <link rel="icon" href="<?= e($icon['url']) ?>" type="<?= e($icon['type']) ?>" sizes="any">
    <link rel="apple-touch-icon" href="<?= e($icon['url']) ?>">
<?php endif; ?>
<?php if ($shareImage !== null): ?>
    <meta property="og:image" content="<?= e($shareImage) ?>">
<?php endif; ?>
<?php /* The two families' first files at once (D-185): drawn in their own face the first time. */ ?>
<?php foreach ($fontPreloads as $font): ?>
    <link rel="preload" href="<?= e($font) ?>" as="font" type="font/woff2" crossorigin>
<?php endforeach; ?>
    <link rel="stylesheet" href="<?= e(Url::stylesheet()) ?>">
<?php /* The nineteen sheets as one file (D-204): one request a first visit waits for, not
         nineteen. Linked one by one only where the bundle could not be written. */ ?>
<?php if ($siteStyles !== ''): ?>
    <link rel="stylesheet" href="<?= e($siteStyles) ?>">
<?php else: ?>
<?php foreach (\App\Support\SiteStyles::FILES as $sheet): ?>
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/' . $sheet)) ?>">
<?php endforeach; ?>
<?php endif; ?>
<?php /* A script a visitor's page loads only when the header has a menu for it to fold: the
         mobile menu and submenu buttons (D-036). Without it nothing breaks. */ ?>
<?php if (str_contains($headerHtml, 'data-site-nav-toggle')): ?>
    <script src="<?= e(Url::versioned('assets/site-nav.js')) ?>" defer></script>
<?php endif; ?>
<?php /* And a second, only on a page with a video or a Google map: it turns the press into
         the frame (D-147). Without it the press opens the video on its own site. */ ?>
<?php if (str_contains($content, 'data-embed-src')): ?>
    <script src="<?= e(Url::versioned('assets/site-embed.js')) ?>" defer></script>
<?php endif; ?>
<?php /* And a third, only where a section arrives as it is scrolled to (D-165). Without it
         every section is simply there. */ ?>
<?php if (str_contains($content, 'data-anim=')): ?>
    <script src="<?= e(Url::versioned('assets/anim.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($designPreview): ?>
    <?php /* PRESSING A PART OF THE PICTURE OPENS ITS SETTINGS (D-181, README 5.7): the parts
             the preview marks (AppearancePreview::region()) and their outlines. */ ?>
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/canvas-regions.css')) ?>">
<?php endif; ?>
</head>
<body<?= $designPreview ? ' class="bx-design-preview"' : '' ?>>
<?php /* THE SHEET (PLAN.md D-031). A boxed page needs something to be a page: <body> holds
         the colour around it and this element is the page itself. Without a wrapper there
         was nothing to inset, because body carried both the background and the content.
         When the page is not boxed the frame token is zero, so this fills the window and
         the colour around it is never seen — which is also why no text can land on it. */ ?>
    <?php /* THE FRAME WRAPS THE SHEET, NOT THE PAGE (D-067). Two slots for the chrome: one
             outside the frame, where it reaches the window's edge, and one inside the
             sheet. Which is used is a decision, read here — the CSS never asks whether the
             page is boxed, which is what keeps it free of conditionals. */ ?>
    <div class="page">
<?php if ($headerBleed === 'full'): ?>
<?= $headerHtml ?>
<?php endif; ?>
        <div class="page-frame">
            <div class="page-sheet">
<?php if ($headerBleed !== 'full'): ?>
<?= $headerHtml ?>
<?php endif; ?>
    <main>
<?= $content ?>
    </main>
<?php if ($footerBleed !== 'full'): ?>
<?= $footerHtml ?>
<?php endif; ?>
            </div>
        </div>
<?php if ($footerBleed === 'full'): ?>
<?= $footerHtml ?>
<?php endif; ?>
    </div>
<?php /* NO SECOND FOOTER HERE. The site has exactly one and the chrome footer is it — it
         carries the language switcher through the same partial this layout used to include.
         Rendering the switcher here as well would put two of them on every translated page,
         which is what "one footer" was decided to prevent (D-028). */ ?>
</body>
</html>
