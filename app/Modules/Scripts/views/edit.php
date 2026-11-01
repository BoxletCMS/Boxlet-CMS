<?php

use App\Modules\Scripts\Scripts;
use App\Support\Url;

/**
 * One of the owner's scripts (PLAN.md O-73): its name, code, place and kind, and the cookies the
 * banner says it sets. One form; Save, and "Use the known cookies" for a service Boxlet
 * recognises, both post it. The cookies are rows, with empty ones under them to add to without a
 * script. Check (admin-scripts-check.js) opens the site with every script allowed and lists what
 * appeared on its domain that no script lists, each to be added here as a row.
 *
 * @var array{id: int, name: string, code: string, place: string, kind: string, cookies: list<array{name: string, provider: string, purpose: string, duration: string}>, enabled: bool, position: int} $script
 * @var array{key: string, name: string, kind: string, cookieless: bool, loads: bool, cookies: list<array{name: string, provider: string, purpose: string, duration: string}>}|null $known
 * @var int $spare
 * @var list<string> $listed every cookie name any script lists
 * @var array<string, string> $errors
 * @var string $csrf
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>'
    : '';
$rows = array_merge($script['cookies'], array_fill(0, $spare, ['name' => '', 'provider' => '', 'purpose' => '', 'duration' => '']));
$missing = $known === null ? [] : array_diff(array_column($known['cookies'], 'name'), array_column($script['cookies'], 'name'));
?>
        <div class="page-header">
            <h1><?= e($script['name']) ?></h1>
        </div>
        <p class="page-subtitle"><a href="<?= e(Url::admin('scripts')) ?>"><?= e(t('scripts.back')) ?></a></p>

        <form method="post" action="<?= e(Url::admin('scripts', $script['id'])) ?>" class="stack scripts-form" id="script-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" name="action" value="save" class="visually-hidden" tabindex="-1" aria-hidden="true"><?= e(t('scripts.save')) ?></button>

            <div class="panel stack">
                <div class="field">
                    <label for="script-name"><?= e(t('scripts.name')) ?></label>
                    <input type="text" id="script-name" name="name" value="<?= e($script['name']) ?>" maxlength="190" required>
                    <?= $error('name') ?>
                </div>
                <div class="field">
                    <label for="script-code"><?= e(t('scripts.code')) ?></label>
                    <textarea id="script-code" name="code" rows="10" class="code-input" spellcheck="false" required aria-describedby="script-code-hint"><?= e($script['code']) ?></textarea>
                    <span class="hint" id="script-code-hint"><?= e(t('scripts.code_hint')) ?></span>
                    <?= $error('code') ?>
                </div>
<?php if ($known !== null): ?>
                <?php /* What Boxlet recognises the code as, and what that means for the banner. */ ?>
                <p class="notice" role="status"><?= e(t($known['cookieless'] ? 'scripts.known_cookieless' : ($known['loads'] ? 'scripts.known_loads' : 'scripts.known'), ['service' => $known['name'], 'kind' => t('scripts.kind.' . $known['kind'])])) ?></p>
<?php endif; ?>
                <div class="field-pair">
                    <div class="field">
                        <label for="script-kind"><?= e(t('scripts.kind')) ?></label>
                        <select id="script-kind" name="kind" aria-describedby="script-kind-hint">
<?php foreach (Scripts::KINDS as $kind): ?>
                            <option value="<?= e($kind) ?>"<?= $kind === $script['kind'] ? ' selected' : '' ?>><?= e(t('scripts.kind.' . $kind)) ?></option>
<?php endforeach; ?>
                        </select>
                        <span class="hint" id="script-kind-hint"><?= e(t('scripts.kind_hint')) ?></span>
                    </div>
                    <div class="field">
                        <label for="script-place"><?= e(t('scripts.place')) ?></label>
                        <select id="script-place" name="place" aria-describedby="script-place-hint">
<?php foreach (Scripts::PLACES as $place): ?>
                            <option value="<?= e($place) ?>"<?= $place === $script['place'] ? ' selected' : '' ?>><?= e(t('scripts.place.' . $place)) ?></option>
<?php endforeach; ?>
                        </select>
                        <span class="hint" id="script-place-hint"><?= e(t('scripts.place_hint')) ?></span>
                    </div>
                </div>
                <label class="checkbox">
                    <input type="checkbox" name="enabled" value="1"<?= $script['enabled'] ? ' checked' : '' ?>>
                    <?= e(t('scripts.enabled')) ?>
                </label>
            </div>

            <div class="panel stack" id="cookies">
                <h2><?= e(t('scripts.cookies')) ?></h2>
                <p class="hint"><?= e(t('scripts.cookies_hint')) ?></p>
<?php if ($missing !== []): ?>
                <div>
                    <button type="submit" name="action" value="known" class="button button-secondary"><?= e(t('scripts.use_known', ['service' => $known['name']])) ?></button>
                </div>
<?php endif; ?>
                <div class="scripts-cookies" data-scripts-cookies>
                    <div class="scripts-cookie scripts-cookie-head" aria-hidden="true">
                        <span><?= e(t('scripts.cookie.name')) ?></span>
                        <span><?= e(t('scripts.cookie.provider')) ?></span>
                        <span><?= e(t('scripts.cookie.purpose')) ?></span>
                        <span><?= e(t('scripts.cookie.duration')) ?></span>
                    </div>
<?php foreach ($rows as $n => $cookie): ?>
                    <div class="scripts-cookie field" data-scripts-cookie>
<?php foreach (['name', 'provider', 'purpose', 'duration'] as $part): ?>
                        <input type="text" name="cookies[<?= $n ?>][<?= $part ?>]" value="<?= e($cookie[$part]) ?>" aria-label="<?= e(t('scripts.cookie.' . $part)) ?>"<?= $part === 'name' ? ' spellcheck="false"' : '' ?>>
<?php endforeach; ?>
                    </div>
<?php endforeach; ?>
                </div>

                <?php /* THE CHECK (O-73): what the site sets on its own domain with every script
                         allowed. Only in this browser, and put back as it was afterwards. */ ?>
                <div class="scripts-check js-only" data-scripts-check data-listed="<?= e(implode(' ', $listed)) ?>" data-home="<?= e(Url::page(Url::primaryLocale())) ?>"
                     data-working="<?= e(t('scripts.check_working')) ?>" data-none="<?= e(t('scripts.check_none')) ?>" data-found="<?= e(t('scripts.check_found')) ?>" data-add="<?= e(t('scripts.check_add')) ?>" data-failed="<?= e(t('scripts.check_failed')) ?>">
                    <button type="button" class="button button-secondary" data-scripts-check-run><?= e(t('scripts.check')) ?></button>
                    <span class="hint"><?= e(t('scripts.check_hint')) ?></span>
                    <div data-scripts-check-result aria-live="polite"></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="action" value="save" class="button"><?= e(t('scripts.save')) ?></button>
            </div>
        </form>
