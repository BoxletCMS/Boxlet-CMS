<?php
/**
 * A video or a map, in the only iframe Boxlet writes.
 *
 * THE SRC IS BUILT, NEVER PASSED THROUGH. App\Support\Embed turns the stored address into a
 * provider and an id and returns one of four fixed addresses; an address it does not
 * recognise returns null and NOTHING IS FRAMED. So the worst a bad value can do is draw an
 * empty block.
 *
 * SANDBOXED. allow-scripts and allow-same-origin are what a player needs to run at all, and
 * they apply to the PROVIDER's origin, not to this site: the frame still cannot reach this
 * page, its cookies or its storage. allow-popups is left out, so nothing in there can open
 * a window over the site.
 *
 * AN UNRECOGNISED ADDRESS SAYS SO IN THE EDITOR. The note is always in the markup and
 * canvas.css shows it; on the page it draws nothing, because a visitor is not the person who
 * can fix it. Same device the Columns block uses for an empty column.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var bool $eager
 * @var string $locale
 *
 * Both strings come through site_t(): the frame's title is read out to a visitor, and the
 * note, though only the editor ever shows it, is drawn by the site renderer in the page's
 * language like everything else here.
 */
$embed = \App\Support\Embed::parse((string) $content['url']);
?>
<figure class="embed ratio-<?= e($content['ratio']) ?><?= $embed === null ? ' is-empty' : '' ?>">
<?php if ($embed !== null): ?>
    <div class="embed-frame">
        <iframe src="<?= e($embed['src']) ?>"
                title="<?= e($content['caption'] !== '' ? $content['caption'] : site_t('site.embed.' . $embed['provider'], $locale)) ?>"
                loading="<?= $eager ? 'eager' : 'lazy' ?>"
                referrerpolicy="no-referrer"
                sandbox="allow-scripts allow-same-origin allow-presentation"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen></iframe>
    </div>
<?php else: ?>
    <p class="embed-unknown"><?= e(site_t('site.embed.unknown', $locale)) ?></p>
<?php endif; ?>
<?php if ($content['caption'] !== ''): ?>
    <figcaption class="embed-caption"><?= e($content['caption']) ?></figcaption>
<?php endif; ?>
</figure>
