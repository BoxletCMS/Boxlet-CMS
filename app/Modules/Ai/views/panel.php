<?php

use App\Modules\Ai\AiSettings;
use App\Support\Url;

/**
 * The AI translation panel on the Settings screen (PLAN.md D-248), with forms of its own. The
 * key is never drawn back: the field says whether one is set, and left empty it is kept.
 *
 * @var array{provider: string, model: string, keep: string, voice: string} $ai what is stored, or what was typed into a refused save
 * @var array<string, string> $aiErrors
 * @var string $aiKeyTrace what the saved key shows in its empty field; '' when none is saved
 * @var array{in: int, out: int} $aiUsage
 * @var array<string, string> $aiModels Anthropic's list as last fetched, else the built-in one (D-255)
 * @var string $csrf
 */
$aiError = static fn (string $key): string => isset($aiErrors[$key])
    ? '<p class="field-error" role="alert">' . e($aiErrors[$key]) . '</p>'
    : '';
$known = isset($aiModels[$ai['model']]);
$recommended = AiSettings::recommended($aiModels);
?>
        <div class="panel stack" id="ai">
            <h2><?= e(t('ai.title')) ?></h2>
            <p class="hint"><?= e(t('ai.intro')) ?></p>
            <p class="<?= $aiKeyTrace !== '' ? 'notice notice-success' : 'hint' ?>"><?= e($aiKeyTrace !== '' ? t('ai.on') : t('ai.off')) ?></p>

            <form method="post" action="<?= e(Url::admin('settings', 'ai')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="ai_provider"><?= e(t('ai.provider')) ?></label>
                    <select id="ai_provider" name="ai_provider">
<?php foreach (AiSettings::PROVIDERS as $provider): ?>
                        <option value="<?= e($provider) ?>"<?= $provider === $ai['provider'] ? ' selected' : '' ?>><?= e(t('ai.provider.' . $provider)) ?></option>
<?php endforeach; ?>
                    </select>
                    <?= $aiError('provider') ?>
                </div>
                <div class="field">
                    <label for="ai_model"><?= e(t('ai.model')) ?></label>
                    <select id="ai_model" name="ai_model" aria-describedby="ai_model-hint" data-ai-model>
<?php foreach ($aiModels as $id => $name): ?>
                        <option value="<?= e($id) ?>"<?= $id === $ai['model'] ? ' selected' : '' ?>><?= e($name . ($id === $recommended ? ' — ' . t('ai.model_recommended') : '')) ?></option>
<?php endforeach; ?>
                        <option value="other"<?= $known ? '' : ' selected' ?>><?= e(t('ai.model_other')) ?></option>
                    </select>
                    <span class="hint" id="ai_model-hint"><?= e(t('ai.model_hint')) ?></span>
                    <?= $aiError('model') ?>
                </div>
                <?php /* Shown by ai-settings.js only while "Another model…" is chosen; without the
                         script it is always there, and its hint says what it is for (D-255). */ ?>
                <div class="field" data-ai-other>
                    <label for="ai_model_other"><?= e(t('ai.model_other_label')) ?></label>
                    <input type="text" id="ai_model_other" name="ai_model_other" value="<?= e($known ? '' : $ai['model']) ?>" autocomplete="off" spellcheck="false" aria-describedby="ai_model_other-hint">
                    <span class="hint" id="ai_model_other-hint"><?= e(t('ai.model_other_hint')) ?></span>
                    <?= $aiError('model_other') ?>
                </div>
                <div class="field">
                    <label for="ai_key"><?= e(t('ai.key')) ?></label>
                    <input type="password" id="ai_key" name="ai_key" value="" autocomplete="off" spellcheck="false" placeholder="<?= e($aiKeyTrace) ?>" aria-describedby="ai_key-hint">
                    <span class="hint" id="ai_key-hint"><?= e($aiKeyTrace !== '' ? t('ai.key_set') : t('ai.key_hint')) ?></span>
                    <?= $aiError('key') ?>
<?php if ($aiKeyTrace !== ''): ?>
                    <label class="checkbox"><input type="checkbox" name="ai_remove_key" value="1"> <?= e(t('ai.key_remove')) ?></label>
<?php endif; ?>
                </div>
                <div class="field">
                    <label for="ai_keep"><?= e(t('ai.keep')) ?></label>
                    <textarea id="ai_keep" name="ai_keep" rows="3" aria-describedby="ai_keep-hint"><?= e($ai['keep']) ?></textarea>
                    <span class="hint" id="ai_keep-hint"><?= e(t('ai.keep_hint')) ?></span>
                    <?= $aiError('keep') ?>
                </div>
                <div class="field">
                    <label for="ai_voice"><?= e(t('ai.voice')) ?></label>
                    <textarea id="ai_voice" name="ai_voice" rows="3" aria-describedby="ai_voice-hint"><?= e($ai['voice']) ?></textarea>
                    <span class="hint" id="ai_voice-hint"><?= e(t('ai.voice_hint')) ?></span>
                </div>
                <p class="hint"><?= e(t('ai.privacy')) ?></p>
                <div class="form-actions">
                    <button type="submit" class="button"><?= e(t('ai.save')) ?></button>
                </div>
            </form>

<?php if ($aiKeyTrace !== ''): ?>
            <form method="post" action="<?= e(Url::admin('settings', 'ai', 'test')) ?>" class="form-actions">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-secondary"><?= e(t('ai.test')) ?></button>
                <span class="hint"><?= e(t('ai.usage', ['in' => \App\Support\AdminLang::number($aiUsage['in']), 'out' => \App\Support\AdminLang::number($aiUsage['out'])])) ?></span>
            </form>
<?php endif; ?>
        </div>
