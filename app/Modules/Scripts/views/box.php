<?php

/**
 * THE BOX THAT ASKS (PLAN.md O-73; the owner: "kutija u kutu"). Drawn hidden in every page that
 * has a script waiting for consent; site-consent.js shows it to a visitor who has not answered,
 * and again from "Cookie settings" in the footer.
 *
 * Accept all and Reject all are the same button, side by side: refusing is as easy as agreeing.
 * Settings opens each kind with its switch, what it is for, and the cookies the owner listed.
 * Without a script nothing waiting for consent ever runs, so the box stays hidden.
 *
 * Required by Consent::box().
 *
 * @var array<string, list<array{name: string, provider: string, purpose: string, duration: string}>> $kinds by kind, necessary first
 * @var string $locale
 */
?>
<div class="consent" data-consent data-consent-key="<?= e(\App\Modules\Scripts\Consent::STORAGE_KEY) ?>" role="region" aria-labelledby="consent-title" tabindex="-1" hidden>
    <p class="consent-title" id="consent-title"><?= e(site_t('site.consent.title', $locale)) ?></p>
    <p class="consent-text"><?= e(site_t('site.consent.text', $locale)) ?></p>
    <div class="consent-choices" id="consent-choices" data-consent-choices hidden>
<?php foreach ($kinds as $kind => $cookies): ?>
        <div class="consent-kind" data-consent-kind-row="<?= e($kind) ?>" data-consent-cookies="<?= e(implode(' ', array_column($cookies, 'name'))) ?>">
            <label class="consent-kind-row">
                <input type="checkbox"<?= $kind === 'necessary' ? ' checked disabled' : ' data-consent-choice="' . e($kind) . '"' ?>>
                <span class="consent-kind-name"><?= e(site_t('site.consent.kind.' . $kind, $locale)) ?></span>
<?php if ($kind === 'necessary'): ?>
                <span class="consent-always"><?= e(site_t('site.consent.always', $locale)) ?></span>
<?php endif; ?>
            </label>
            <p class="consent-kind-text"><?= e(site_t('site.consent.kind.' . $kind . '_text', $locale)) ?></p>
<?php if ($cookies === []): ?>
            <p class="consent-none"><?= e(site_t('site.consent.none_listed', $locale)) ?></p>
<?php else: ?>
            <details class="consent-cookies">
                <summary><?= e(site_t('site.consent.cookies', $locale)) ?></summary>
                <?php /* A list, not a table: four columns do not fit a box a phone can hold. */ ?>
                <ul class="consent-cookie-list">
<?php foreach ($cookies as $cookie): ?>
                    <li>
                        <code><?= e($cookie['name']) ?></code><?= implode('', array_map(static fn (string $part): string => ' · ' . e($part), array_filter([$cookie['provider'], $cookie['duration']], static fn (string $part): bool => $part !== ''))) ?>
<?php if ($cookie['purpose'] !== ''): ?>
                        <span class="consent-cookie-purpose"><?= e($cookie['purpose']) ?></span>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
            </details>
<?php endif; ?>
        </div>
<?php endforeach; ?>
    </div>
    <div class="consent-actions">
        <button type="button" class="button consent-button" data-consent-all="no"><?= e(site_t('site.consent.reject', $locale)) ?></button>
        <button type="button" class="button consent-button" data-consent-all="yes"><?= e(site_t('site.consent.accept', $locale)) ?></button>
        <button type="button" class="button consent-button" data-consent-save hidden><?= e(site_t('site.consent.save', $locale)) ?></button>
        <button type="button" class="consent-more" data-consent-more aria-expanded="false" aria-controls="consent-choices"><?= e(site_t('site.consent.more', $locale)) ?></button>
    </div>
</div>
