<?php

/**
 * The bar a logged-in admin sees on the real site while maintenance is on (D-021).
 *
 * Its rules live in maintenance-bar.css, not in an inline <style> here. An inline block is
 * refused by any Content Security Policy without 'unsafe-inline' — the admin's already is,
 * and the front end's may be — and a style that silently does not apply leaves a bar the
 * owner cannot see saying the site is hidden. The class names are prefixed because this
 * renders inside the site's own document, where nothing else is ours.
 *
 * The stylesheet is linked here rather than from the site's layout, so it is fetched only
 * when the bar is shown: a visitor to a healthy site never requests it. A <link> in the
 * body is valid HTML5 and applies to the whole document.
 *
 * Provided by View::render() with no layout.
 *
 * @var string $off URL of the toggle that switches maintenance off
 */

use App\Support\Url;

?>
<link rel="stylesheet" href="<?= e(Url::versioned('assets/maintenance-bar.css')) ?>">
<div class="boxlet-maintenance-bar" role="status">
    <span><?= e(t('maintenance.bar')) ?></span>
    <a href="<?= e($off) ?>"><?= e(t('maintenance.bar_off')) ?></a>
</div>
