<?php

use App\Modules\Stats\Tracker;
use App\Support\Url;

/**
 * The Statistics panel on the Settings screen (PLAN.md D-051): on or off, whether a
 * browser's Do Not Track or Global Privacy Control is honoured, how long counts are kept,
 * and a way to delete them all. Its forms are its own, outside the settings form.
 *
 * @var array{enabled: bool, dnt: bool, retention: int, missing: bool, group: bool, location: string, cityMonths: int, cityMin: int} $stats
 * @var array{built: string, type: string, cities: bool}|null $geo the location database in use
 * @var array{done: int, total: int}|null $geoDownload a city database part-way down (D-055)
 * @var string $uploadLimit the largest file this server accepts, as php.ini says it
 * @var string $trustedProxies the addresses that may speak for a visitor, one per line
 * @var array<string, array{language: string, text: string}> $privacy the suggested policy text, by language
 * @var string $csrf
 */
?>
        <div class="panel stack" id="statistics">
            <h2><?= e(t('stats.title')) ?></h2>
            <p class="hint"><?= e(t('stats.intro')) ?></p>
            <p><?= e(t($stats['enabled'] ? 'stats.on_now' : 'stats.off_now')) ?></p>

            <form method="post" action="<?= e(Url::admin('settings', 'statistics')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label class="checkbox"><input type="checkbox" name="stats_enabled" value="1"<?= $stats['enabled'] ? ' checked' : '' ?>> <span><?= e(t('stats.enabled')) ?></span></label>
                <label class="checkbox"><input type="checkbox" name="stats_dnt" value="1"<?= $stats['dnt'] ? ' checked' : '' ?>> <span><?= e(t('stats.dnt')) ?></span></label>
                <label class="checkbox"><input type="checkbox" name="stats_group" value="1"<?= $stats['group'] ? ' checked' : '' ?>> <span><?= e(t('stats.group_small', ['count' => (string) \App\Modules\Stats\StatsQuery::SMALL])) ?></span></label>
                <label class="checkbox"><input type="checkbox" name="stats_missing" value="1"<?= $stats['missing'] ? ' checked' : '' ?>> <span><?= e(t('stats.missing_count')) ?></span></label>
                <?php /* How much of where a visitor is (D-055). A radio group, because the
                         three are one choice and each has to say what it means. */ ?>
                <fieldset class="fieldset stack">
                    <legend><?= e(t('stats.location')) ?></legend>
<?php foreach (App\Modules\Stats\Place::LEVELS as $level): ?>
                    <label class="checkbox"><input type="radio" name="stats_location" value="<?= e($level) ?>"<?= $stats['location'] === $level ? ' checked' : '' ?>> <span><?= e(t('stats.location_' . $level)) ?></span></label>
<?php endforeach; ?>
                    <span class="hint"><?= e(t('stats.location_hint')) ?></span>
<?php if ($stats['location'] !== 'country' && ($geo === null || !$geo['cities'])): ?>
                    <p class="notice notice-warning"><?= e(t('stats.location_needs_city')) ?></p>
<?php endif; ?>
                    <?php /* HOW MANY VISITORS A CITY NEEDS BEFORE IT IS NAMED (D-109).
                             Fixed at five until the owner asked for it: on a site with
                             twenty visitors a month the Cities panel showed nothing but
                             "Other", which is the feature not working rather than the
                             feature protecting anybody. One is offered and is a real
                             choice — the warning under it is not decoration, and the
                             site's own privacy text drops a sentence when it is chosen. */ ?>
                    <div class="field">
                        <div class="choice-head">
                            <span class="choice-name" id="stats_city_min-label"><?= e(t('stats.city_min')) ?></span>
                        </div>
                        <?= segmented_group('stats_city_min', array_combine(
                            App\Modules\Stats\PlaceQuery::MIN_CHOICES,
                            array_map('strval', App\Modules\Stats\PlaceQuery::MIN_CHOICES),
                        ), (string) $stats['cityMin'], 'stats_city_min-label', 'stats_city_min-') ?>
                        <span class="hint"><?= e(t('stats.city_min_hint')) ?></span>
<?php if ($stats['cityMin'] < 2): ?>
                        <p class="notice notice-warning"><?= e(t('stats.city_min_one')) ?></p>
<?php endif; ?>
                    </div>
                    <?php /* The city is the sharpest thing these counts hold, so it is the
                             first thing forgotten (D-055): after this, a place keeps its
                             region and the counts stay whole. */ ?>
                    <div class="field">
                        <label for="stats_city_months"><?= e(t('stats.city_months')) ?></label>
                        <select id="stats_city_months" name="stats_city_months" aria-describedby="stats_city_months-hint">
<?php foreach (Tracker::CITY_MONTHS as $months): ?>
                            <option value="<?= e($months) ?>"<?= $months === $stats['cityMonths'] ? ' selected' : '' ?>><?= e(match ($months) {
                                0 => t('stats.city_months_all'),
                                1 => t('stats.months_one'),
                                default => t('stats.months', ['months' => (string) $months]),
                            }) ?></option>
<?php endforeach; ?>
                        </select>
                        <span class="hint" id="stats_city_months-hint"><?= e(t('stats.city_months_hint')) ?></span>
                    </div>
                </fieldset>
                <div class="field">
                    <label for="stats_retention"><?= e(t('stats.retention')) ?></label>
                    <select id="stats_retention" name="stats_retention" aria-describedby="stats_retention-hint">
<?php foreach (Tracker::RETENTION as $months): ?>
                        <option value="<?= e($months) ?>"<?= $months === $stats['retention'] ? ' selected' : '' ?>><?= e(t('stats.months', ['months' => (string) $months])) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="hint" id="stats_retention-hint"><?= e(t('stats.retention_hint')) ?></span>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button button-secondary"><?= e(t('stats.save')) ?></button>
                </div>
            </form>

            <?php /* Behind a proxy (O-20): the field that says which machines may speak for a
                     visitor. Here, because this is where the owner reads about addresses —
                     and the hint says it is not only statistics that use it. */ ?>
            <div class="field">
                <label for="trusted_proxies"><?= e(t('stats.proxies')) ?></label>
                <textarea id="trusted_proxies" name="trusted_proxies" rows="2" spellcheck="false" aria-describedby="trusted_proxies-hint"><?= e($trustedProxies) ?></textarea>
                <span class="hint" id="trusted_proxies-hint"><?= e(t('stats.proxies_hint')) ?></span>
            </div>

            <?php /* The country database (D-051): optional, fetched only when asked. */ ?>
            <fieldset class="fieldset stack">
                <legend><?= e(t('stats.geo_title')) ?></legend>
                <p class="hint"><?= e(t('stats.geo_intro')) ?></p>
                <p><?= e($geo === null ? t('stats.geo_none') : t('stats.geo_in_use', ['date' => $geo['built']]) . ' ' . t($geo['cities'] ? 'stats.geo_kind_city' : 'stats.geo_kind_country')) ?></p>
<?php if ($geoDownload !== null): ?>
                <?php /* A city database part-way down (D-055): how far it got, and the button
                         that fetches the next piece. auto-continue.js presses it by itself,
                         so with a script the whole file arrives on its own. */ ?>
                <p><?= e(t('stats.geo_downloading', [
                    'done' => App\Support\Bytes::human($geoDownload['done']),
                    'total' => App\Support\Bytes::human($geoDownload['total']),
                ])) ?></p>
                <div class="form-actions">
                    <form method="post" action="<?= e(Url::admin('settings', 'statistics', 'cities', 'step')) ?>" data-auto-continue>
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="button button-secondary"><?= e(t('stats.geo_continue')) ?></button>
                    </form>
                    <form method="post" action="<?= e(Url::admin('settings', 'statistics', 'cities', 'cancel')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="button button-ghost"><?= e(t('stats.geo_cancel')) ?></button>
                    </form>
                </div>
<?php else: ?>
                <div class="form-actions">
                    <form method="post" action="<?= e(Url::admin('settings', 'statistics', 'countries')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="button button-secondary"><?= e(t($geo === null ? 'stats.geo_download' : 'stats.geo_update')) ?></button>
                    </form>
                    <form method="post" action="<?= e(Url::admin('settings', 'statistics', 'cities')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="button button-secondary"><?= e(t('stats.geo_city_download')) ?></button>
                    </form>
                </div>
                <p class="hint"><?= e(t('stats.geo_city_hint')) ?></p>
<?php endif; ?>
                <form method="post" action="<?= e(Url::admin('settings', 'statistics', 'countries', 'upload')) ?>" enctype="multipart/form-data" class="stack">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <div class="field">
                        <label for="geo_file"><?= e(t('stats.geo_upload')) ?></label>
                        <input type="file" id="geo_file" name="geo_file" accept=".mmdb,.gz" aria-describedby="geo_file-hint">
                        <span class="hint" id="geo_file-hint"><?= e(t('stats.geo_upload_hint', ['limit' => $uploadLimit])) ?></span>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="button button-secondary"><?= e(t('stats.geo_upload_button')) ?></button>
                    </div>
                </form>
            </fieldset>

            <?php /* What to tell visitors (D-051): the text written for these settings, in
                     each language there is one for, in a field that selects and copies easily. */ ?>
            <fieldset class="fieldset stack">
                <legend><?= e(t('stats.privacy_title')) ?></legend>
                <p class="hint"><?= e(t('stats.privacy_intro')) ?></p>
<?php foreach ($privacy as $code => $version): ?>
                <details class="stats-privacy">
                    <summary><?= e($version['language']) ?></summary>
                    <div class="field">
                        <label for="stats-privacy-<?= e($code) ?>" class="visually-hidden"><?= e(t('stats.privacy_title') . ' — ' . $version['language']) ?></label>
                        <textarea id="stats-privacy-<?= e($code) ?>" rows="14" readonly lang="<?= e($code) ?>"><?= e($version['text']) ?></textarea>
                    </div>
                </details>
<?php endforeach; ?>
            </fieldset>

            <form method="post" action="<?= e(Url::admin('settings', 'statistics', 'erase')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-ghost button-danger" data-confirm="<?= e(t('stats.erase_confirm')) ?>"><?= e(t('stats.erase')) ?></button>
            </form>
        </div>
