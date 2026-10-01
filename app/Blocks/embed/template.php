<?php
/**
 * A video or a map, in the only iframe Boxlet writes — and for a video or a Google map, not
 * until the visitor asks for it (PLAN.md D-147).
 *
 * THE SRC IS BUILT, NEVER PASSED THROUGH. App\Support\Embed turns the stored address into a
 * provider and an id and returns one of four fixed addresses; an address it does not
 * recognise returns null and NOTHING IS FRAMED. So the worst a bad value can do is draw an
 * empty block.
 *
 * NOTHING OF YOUTUBE'S, VIMEO'S OR GOOGLE'S LOADS UNTIL IT IS PRESSED. Even youtube-nocookie
 * wrote two localStorage keys and an IndexedDB database into a visitor's browser, and made
 * seven requests to Google, the moment the page opened (measured 2026-09-30) — storage the
 * law treats as a cookie, on a site that promises none. So these providers are drawn as a
 * link: the cover picture, which is the site's own (a still fetched once by the admin, never
 * by the visitor), with Play. A YouTube video's Play is YouTube's own red button, an image
 * (embed-youtube.svg) rather than a colour of the site's (the owner, D-149); its words stay
 * for a screen reader. site-embed.js turns a press into the
 * frame, playing; without it, the link opens the video or the map on its own site. An
 * OpenStreetMap map stores nothing and is framed at once, as before.
 *
 * SANDBOXED. allow-scripts and allow-same-origin are what a player needs to run at all, and
 * they apply to the PROVIDER's origin, not to this site: the frame still cannot reach this
 * page, its cookies or its storage. allow-popups is left out, so nothing in there can open
 * a window over the site. site-embed.js builds the deferred frame with these same attributes,
 * read from the link, so there is one list of them and it is here.
 *
 * THE SITE'S ORIGIN GOES WITH THE REQUEST, NEVER THE PAGE'S PATH (PLAN.md D-146). YouTube
 * answers a frame that names no site with "Error 153, video player configuration error".
 *
 * AN UNRECOGNISED ADDRESS SAYS SO IN THE EDITOR. The note is always in the markup and
 * canvas.css shows it; on the page it draws nothing, because a visitor is not the person who
 * can fix it. Same device the Columns block uses for an empty column.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media
 * @var bool $eager
 * @var string $locale
 *
 * Every string comes through site_t(): the frame's title and the Play label are read out to
 * a visitor, and the note, though only the editor ever shows it, is drawn by the site
 * renderer in the page's language like everything else here.
 */
$embed = \App\Support\Embed::parse((string) $content['url']);
$title = $embed === null ? '' : ($content['caption'] !== '' ? $content['caption'] : site_t('site.embed.' . $embed['provider'], $locale));
$sandbox = 'allow-scripts allow-same-origin allow-presentation';
$allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture';
?>
<figure class="embed ratio-<?= e($content['ratio']) ?><?= $embed === null ? ' is-empty' : '' ?>">
<?php if ($embed !== null && $embed['deferred']):
    $poster = is_int($content['poster'] ?? null) ? ($media[$content['poster']] ?? null) : null;
    $tag = \App\Modules\Media\MediaPicture::tag($poster, ['wide', 'hero', 'full'], $layout === 'inset' ? '(max-width: 48rem) 100vw, 48rem' : '100vw', $eager);
    $video = $embed['provider'] !== 'googlemaps';
    ?>
    <div class="embed-frame">
        <a class="embed-play<?= $tag === '' ? ' is-bare' : '' ?> provider-<?= e($embed['provider']) ?>" href="<?= e($embed['open']) ?>" target="_blank" rel="noopener"
           data-embed-src="<?= e($embed['src'] . ($video ? (str_contains($embed['src'], '?') ? '&' : '?') . 'autoplay=1' : '')) ?>"
           data-embed-title="<?= e($title) ?>" data-embed-sandbox="<?= e($sandbox) ?>" data-embed-allow="<?= e($allow) ?>">
<?php if ($tag !== ''): ?>
            <?= $tag ?>
<?php endif; ?>
            <span class="embed-play-label">
<?php if ($embed['provider'] === 'youtube'): ?>
                <span class="embed-play-youtube" aria-hidden="true"></span>
<?php endif; ?>
                <span class="embed-play-text"><?= e(site_t($video ? 'site.embed.play' : 'site.embed.show_map', $locale)) ?></span>
            </span>
        </a>
    </div>
<?php elseif ($embed !== null): ?>
    <div class="embed-frame">
        <iframe src="<?= e($embed['src']) ?>"
                title="<?= e($title) ?>"
                loading="<?= $eager ? 'eager' : 'lazy' ?>"
                referrerpolicy="strict-origin-when-cross-origin"
                sandbox="<?= e($sandbox) ?>"
                allow="<?= e($allow) ?>"
                allowfullscreen></iframe>
    </div>
<?php else: ?>
    <p class="embed-unknown"><?= e(site_t('site.embed.unknown', $locale)) ?></p>
<?php endif; ?>
<?php if ($content['caption'] !== ''): ?>
    <figcaption class="embed-caption"><?= e($content['caption']) ?></figcaption>
<?php endif; ?>
</figure>
