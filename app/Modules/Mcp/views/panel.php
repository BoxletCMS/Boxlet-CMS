<?php

use App\Support\Url;

/**
 * AI ACCESS on the Settings screen (PLAN.md O-78): the address, a key just made (shown this once,
 * with the line that connects Claude Code to it), the keys, and a new one, which reads, writes drafts
 * or also publishes (D-291: no checkbox for every key, and no Save). Fed by
 * McpSettingsController::panel().
 *
 * @var array{endpoint: string, keys: list<array{id: int, name: string, can_write: bool, can_publish: bool, created_at: string, last_used_at: string|null, revoked_at: string|null}>, newKey: array{name: string, key: string}|null} $mcp
 * @var string $csrf
 */
?>
        <div class="panel stack" id="mcp">
            <h2><?= e(t('mcp.title')) ?></h2>
            <p class="hint"><?= e(t('mcp.intro')) ?></p>
            <div class="field">
                <label for="mcp-endpoint"><?= e(t('mcp.endpoint')) ?></label>
                <input type="text" id="mcp-endpoint" value="<?= e($mcp['endpoint']) ?>" readonly spellcheck="false" class="code-input">
            </div>

<?php if ($mcp['newKey'] !== null): ?>
            <?php /* SHOWN ONCE: the key is kept only as its hash from here on. */ ?>
            <div class="notice notice-success stack" role="status">
                <p><?= e(t('mcp.new_key', ['name' => $mcp['newKey']['name']])) ?></p>
                <div class="field">
                    <label for="mcp-new-key"><?= e(t('mcp.key')) ?></label>
                    <input type="text" id="mcp-new-key" value="<?= e($mcp['newKey']['key']) ?>" readonly spellcheck="false" class="code-input">
                </div>
                <div class="field">
                    <label for="mcp-connect"><?= e(t('mcp.connect')) ?></label>
                    <textarea id="mcp-connect" rows="2" readonly spellcheck="false" class="code-input">claude mcp add --transport http boxlet <?= e($mcp['endpoint']) ?> --header "Authorization: Bearer <?= e($mcp['newKey']['key']) ?>"</textarea>
                    <span class="hint"><?= e(t('mcp.connect_hint')) ?></span>
                </div>
            </div>
<?php endif; ?>

<?php if ($mcp['keys'] !== []): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('mcp.col.name')) ?></th>
                            <th scope="col"><?= e(t('mcp.col.may')) ?></th>
                            <th scope="col"><?= e(t('mcp.col.used')) ?></th>
                            <th scope="col"><span class="visually-hidden"><?= e(t('mcp.col.actions')) ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($mcp['keys'] as $key): ?>
                        <tr>
                            <td class="row-title"><?= e($key['name']) ?></td>
                            <td><?= e(t($key['can_publish'] ? 'mcp.may.publish' : ($key['can_write'] ? 'mcp.may.write' : 'mcp.may.read'))) ?></td>
                            <td><?= e($key['last_used_at'] === null ? t('mcp.never') : substr($key['last_used_at'], 0, 16)) ?></td>
                            <td class="row-actions">
<?php if ($key['revoked_at'] !== null): ?>
                                <span class="badge"><?= e(t('mcp.revoked_badge')) ?></span>
                                <form method="post" action="<?= e(Url::admin('settings', 'mcp', 'keys', $key['id'], 'remove')) ?>">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <button type="submit" class="button button-ghost"><?= e(t('mcp.remove')) ?></button>
                                </form>
<?php else: ?>
                                <form method="post" action="<?= e(Url::admin('settings', 'mcp', 'keys', $key['id'], 'revoke')) ?>">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <button type="submit" class="button button-ghost button-danger" data-confirm="<?= e(t('mcp.revoke_confirm', ['name' => $key['name']])) ?>"><?= e(t('mcp.revoke')) ?></button>
                                </form>
<?php endif; ?>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php endif; ?>

            <form method="post" action="<?= e(Url::admin('settings', 'mcp', 'keys')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field-pair">
                    <div class="field">
                        <label for="mcp-name"><?= e(t('mcp.name')) ?></label>
                        <input type="text" id="mcp-name" name="name" maxlength="190" placeholder="<?= e(t('mcp.key_default')) ?>" aria-describedby="mcp-name-hint">
                        <span class="hint" id="mcp-name-hint"><?= e(t('mcp.name_hint')) ?></span>
                    </div>
                    <div class="field">
                        <label for="mcp-write"><?= e(t('mcp.may')) ?></label>
                        <select id="mcp-write" name="write" aria-describedby="mcp-write-hint">
                            <option value="0"><?= e(t('mcp.may.read')) ?></option>
                            <option value="1"><?= e(t('mcp.may.write')) ?></option>
                            <option value="2"><?= e(t('mcp.may.publish')) ?></option>
                        </select>
                        <span class="hint" id="mcp-write-hint"><?= e(t('mcp.may_hint')) ?></span>
                    </div>
                </div>
                <div>
                    <button type="submit" class="button button-secondary"><?= e(t('mcp.create')) ?></button>
                </div>
            </form>
        </div>
