<?php

use App\Support\Url;

/**
 * The language switcher (D-028, 5c).
 *
 * Extracted from layout.php rather than copied into the footer: the site gets exactly one
 * footer, and when the owner's own footer arrives it includes this instead of growing a
 * second copy of the same markup. Two footers on one page, or two switchers, would be the
 * kind of duplication that drifts apart a slice later.
 *
 * Renders nothing at all when only one locale is enabled, which is the behaviour it has
 * always had — a switcher offering one choice is a control with nothing to do.
 *
 * @var string $locale the locale being rendered
 * @var array<int, array<string, mixed>> $locales the languages offered: code, label, and url — this
 *      page in that language, or its home where there is no translation (D-043)
 */
if (count($locales) < 2) {
    return;
}

/*
 * NOT t(), which is the admin's language: site_t(), the few words the site itself says to
 * a visitor, in the page's language (lang/{code}/site.php, D-044).
 */
$label = site_t('site.languages', $locale);
?>
<nav class="locale-switcher" aria-label="<?= e($label) ?>">
<?php foreach ($locales as $option): ?>
    <a href="<?= e((string) ($option['url'] ?? Url::page((string) $option['code']))) ?>" hreflang="<?= e($option['code']) ?>" lang="<?= e($option['code']) ?>"<?= $option['code'] === $locale ? ' aria-current="true"' : '' ?>><?= e($option['label']) ?></a>
<?php endforeach; ?>
</nav>
