<?php

/**
 * PATTERNS IN THE PLAIN EDITOR (PLAN.md D-173): put one in at the end of the page, keep one of
 * the page's sections as one, or let one of yours go. The builder's Add → Patterns comes with
 * its shell (phase 4); these are ordinary submits of the editor's form, so they work without
 * a script, and each saves the draft as it stands first.
 *
 * @var array{mine: list<array{id: int, name: string, created_at: string}>, set: list<array{id: string, name: string}>} $patterns
 * @var array<string, array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}> $sections
 * @var list<array<string, mixed>> $blocks
 * @var array<string, string> $errors
 */
$first = [];
foreach ($blocks as $block) {
    $first[(string) ($block['section'] ?? '')] ??= (string) $block['type'];
}
$choices = [];
$n = 0;
foreach ($sections as $key => $section) {
    if (!isset($first[$key])) {
        continue;
    }
    $n++;
    $name = (string) ($section['style']['name'] ?? '');
    $choices[$key] = t('patterns.section_n', ['n' => $n]) . ' — ' . ($name !== '' ? $name : t('block.' . $first[$key]));
}
?>
            <details class="panel editor-patterns"<?= isset($errors['pattern']) ? ' open' : '' ?>>
                <summary><?= e(t('patterns.title')) ?></summary>
                <div class="field">
                    <label for="pattern"><?= e(t('patterns.insert_label')) ?></label>
                    <select id="pattern" name="pattern"<?= $patterns['mine'] === [] && $patterns['set'] === [] ? ' disabled' : '' ?>>
<?php if ($patterns['mine'] === [] && $patterns['set'] === []): ?>
                        <option value=""><?= e(t('patterns.none')) ?></option>
<?php endif; ?>
<?php if ($patterns['mine'] !== []): ?>
                        <optgroup label="<?= e(t('patterns.mine')) ?>">
<?php foreach ($patterns['mine'] as $pattern): ?>
                            <option value="user:<?= e($pattern['id']) ?>"><?= e($pattern['name']) ?></option>
<?php endforeach; ?>
                        </optgroup>
<?php endif; ?>
<?php if ($patterns['set'] !== []): ?>
                        <optgroup label="<?= e(t('patterns.from_set')) ?>">
<?php foreach ($patterns['set'] as $pattern): ?>
                            <option value="set:<?= e($pattern['id']) ?>"><?= e($pattern['name']) ?></option>
<?php endforeach; ?>
                        </optgroup>
<?php endif; ?>
                    </select>
                </div>
                <button type="submit" name="action" value="pattern-insert" class="button button-secondary"<?= $patterns['mine'] === [] && $patterns['set'] === [] ? ' disabled' : '' ?>><?= e(t('patterns.insert')) ?></button>

<?php if ($choices !== []): ?>
                <div class="field">
                    <label for="pattern-section"><?= e(t('patterns.save_label')) ?></label>
                    <select id="pattern-section" name="pattern_section">
<?php foreach ($choices as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="pattern-name"><?= e(t('patterns.name')) ?></label>
                    <input type="text" id="pattern-name" name="pattern_name" maxlength="<?= e(\App\Modules\Pages\PagePattern::NAME) ?>">
<?php if (isset($errors['pattern'])): ?>
                    <p class="field-error" role="alert"><?= e($errors['pattern']) ?></p>
<?php endif; ?>
                </div>
                <button type="submit" name="action" value="pattern-save" class="button button-secondary"><?= e(t('patterns.save')) ?></button>
<?php endif; ?>

<?php if ($patterns['mine'] !== []): ?>
                <ul class="editor-patterns-mine">
<?php foreach ($patterns['mine'] as $pattern): ?>
                    <li><span><?= e($pattern['name']) ?></span>
                        <button type="submit" name="action" value="pattern-delete-<?= e($pattern['id']) ?>" class="button button-ghost button-danger" data-confirm="<?= e(t('patterns.delete_confirm', ['name' => $pattern['name']])) ?>"><?= e(t('patterns.delete')) ?></button></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </details>
