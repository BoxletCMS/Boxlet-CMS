<?php

use App\Modules\Pages\PageSeo;
use App\Support\Url;

/**
 * The rail's Page tab (README 4.2): title, address with the whole of it, parent; the SEO title
 * and description with a picture of a search result; the publish history, each kept page
 * restored into the draft. Every field is bound to the document by builder-page.js; none is
 * posted.
 *
 * @var array<string, mixed> $page
 * @var array<string, mixed> $document
 * @var list<array{id: int, title: string, depth: int}> $parents
 * @var array<int, string> $parentUrls page id => its address, for the whole address shown live
 * @var list<array{id: int, created_at: string}> $revisions
 * @var string $zone
 * @var string $siteName
 */
$seo = PageSeo::of(['seo_json' => (string) ($document['seo_json'] ?? '{}')]);
$home = Url::page((string) $page['locale'], '');
?>
            <section class="pb-panel pb-page" data-pb-panel="page" aria-label="<?= e(t('builder.rail.page')) ?>" hidden>
                <div class="pb-panel-head"><h2><?= e(t('builder.page.title')) ?></h2></div>
                <div class="field">
                    <label for="pb-title"><?= e(t('pages.field.title')) ?></label>
                    <input type="text" id="pb-title" data-pb-page="title" value="<?= e((string) $document['title']) ?>" maxlength="255" required>
                </div>
                <div class="field">
                    <label for="pb-slug"><?= e(t('pages.field.slug')) ?></label>
                    <input type="text" id="pb-slug" data-pb-page="slug" value="<?= e((string) $document['slug']) ?>" maxlength="100" autocapitalize="off" spellcheck="false">
                    <p class="hint-line" data-pb-slug-hint data-home-hint="<?= e(t('pages.slug.home')) ?>" data-auto-hint="<?= e(t('pages.slug.auto')) ?>"></p>
                    <p class="hint-line" data-pb-address data-home="<?= e($home) ?>"></p>
                </div>
                <div class="field">
                    <label for="pb-parent"><?= e(t('pages.field.parent')) ?></label>
                    <select id="pb-parent" data-pb-page="parent_id">
                        <option value="" data-url="<?= e($home) ?>"><?= e(t('pages.parent.none')) ?></option>
<?php foreach ($parents as $option): ?>
                        <option value="<?= e($option['id']) ?>" data-url="<?= e($parentUrls[$option['id']] ?? '') ?>"<?= (int) ($document['parent_id'] ?? 0) === $option['id'] ? ' selected' : '' ?>><?= e(str_repeat('— ', $option['depth']) . $option['title']) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <h3 class="pb-add-group"><?= e(t('builder.page.seo')) ?></h3>
                <div class="field">
                    <label for="pb-seo-title"><?= e(t('pages.field.seo_title')) ?></label>
                    <input type="text" id="pb-seo-title" data-pb-page="seo_title" value="<?= e($seo['title']) ?>" maxlength="255" placeholder="<?= e((string) $document['title']) ?>">
                </div>
                <div class="field">
                    <label for="pb-seo-description"><?= e(t('pages.field.seo_description')) ?></label>
                    <textarea id="pb-seo-description" data-pb-page="seo_description" rows="3"><?= e($seo['description']) ?></textarea>
                </div>
                <label class="checkbox"><input type="checkbox" data-pb-page="noindex" value="1"<?= $seo['noindex'] ? ' checked' : '' ?>> <?= e(t('pages.field.seo_noindex')) ?></label>
                <div class="pb-serp" aria-label="<?= e(t('builder.page.preview_title')) ?>" data-site="<?= e($siteName) ?>">
                    <p class="pb-serp-title" data-pb-serp-title></p>
                    <p class="pb-serp-url" data-pb-serp-url></p>
                    <p class="pb-serp-text" data-pb-serp-text></p>
                </div>
                <h3 class="pb-add-group"><?= e(t('builder.page.history')) ?></h3>
<?php if ($revisions !== []): ?>
                <?php /* What Restore does, said where it is pressed: not a hint behind the toggle (D-088). */ ?>
                <p class="hint-line" data-pb-history-note><?= e(t('pages.history_hint', ['count' => \App\Modules\Pages\PageRevision::KEEP])) ?></p>
<?php endif; ?>
<?php if ($revisions === []): ?>
                <p class="pb-add-none"><?= e(t('builder.page.history_none')) ?></p>
<?php else: ?>
                <ul class="pb-history">
<?php foreach ($revisions as $revision): ?>
                    <li><span data-pb-history-when><?= icon('history') ?> <?= e(\App\Support\Dates::localToSecond($revision['created_at'], $zone)) ?></span>
                        <button type="button" class="link-button" data-pb-restore="<?= e($revision['id']) ?>"><?= e(t('builder.page.restore')) ?></button></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>
