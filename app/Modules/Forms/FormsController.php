<?php

namespace App\Modules\Forms;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Support\Secret;
use App\Support\Url;

/**
 * Admin: forms (PLAN.md D-046) — the list, a new form, and the edit screen with its fields.
 *
 * EVERY BUTTON ON THE EDIT SCREEN SAVES. Add a field, remove one, move one: each posts the
 * whole form with an `action`, which is applied and then stored, and the screen comes back
 * with it done. No script is needed for any of it, and nothing typed is ever lost to a
 * button that only rearranged the screen — which is what the pages' plain editor taught.
 */
final class FormsController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        return $this->list([], 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $name = trim($request->input('name'));
        $wanted = trim($request->input('locale'));
        $errors = [];
        if ($name === '') {
            $errors['name'] = t('forms.name_required');
        }
        if (!in_array($wanted, array_column($this->container->get('locales'), 'code'), true)) {
            $errors['locale'] = t('forms.locale_invalid');
        }
        if ($errors !== []) {
            return $this->list($errors, 422);
        }
        $id = Form::create($this->db(), $wanted, mb_substr($name, 0, 190));
        Activity::record($this->db(), 'form', 'created', $id, mb_substr($name, 0, 190));
        $this->container->get('session')->set('flash', t('forms.created'));

        return Response::redirect(Url::admin('forms', $id));
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, string $locale, array $params): Response
    {
        $form = Form::find($this->db(), (int) $params['id']);
        if ($form === null) {
            return Response::admin(e(t('forms.not_found')), 404);
        }

        return $this->screen($form, [], 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $form = Form::find($db, (int) $params['id']);
        if ($form === null) {
            return Response::admin(e(t('forms.not_found')), 404);
        }

        $rows = is_array($request->body['fields'] ?? null) ? array_values($request->body['fields']) : [];
        $action = $request->input('action');
        if (preg_match('~^(up|down|remove)-(\d+)$~', $action, $match) === 1) {
            $at = (int) $match[2];
            if ($match[1] === 'remove' && isset($rows[$at]) && is_array($rows[$at])) {
                $rows[$at]['remove'] = '1';
            } else {
                $to = $match[1] === 'up' ? $at - 1 : $at + 1;
                if (isset($rows[$at], $rows[$to])) {
                    [$rows[$at], $rows[$to]] = [$rows[$to], $rows[$at]];
                }
            }
        }
        $settings = is_array($request->body['settings'] ?? null) ? $request->body['settings'] : [];
        $parsed = Form::parse($rows, $settings, $action === 'add');
        $name = mb_substr(trim($request->input('name')), 0, 190);
        if ($name === '') {
            $parsed['errors']['name'] = t('forms.name_required');
        }

        // The secret a form's messages are signed with: its own, kept; made when an address is
        // first given (O-77). Never posted, so never taken from what was posted.
        $parsed['settings']['webhook_secret'] = $form['settings']['webhook_secret'];
        if ($parsed['settings']['webhook'] !== '' && $parsed['settings']['webhook_secret'] === '') {
            $parsed['settings']['webhook_secret'] = Secret::seal(FormWebhook::newSecret(), $this->appKey());
        }
        $candidate = ['name' => $name, 'fields' => $parsed['fields'], 'settings' => $parsed['settings']] + $form;
        if ($parsed['errors'] !== []) {
            return $this->screen($candidate, $parsed['errors'], 422);
        }
        Form::update($db, $form['id'], $name, $parsed['fields'], $parsed['settings']);
        Activity::record($db, 'form', 'saved', (int) $form['id'], $name);
        $this->container->get('session')->set('flash', t(match (true) {
            $action === 'add' => 'forms.field_added',
            str_starts_with($action, 'remove-') => 'forms.field_removed',
            str_starts_with($action, 'up-'), str_starts_with($action, 'down-') => 'forms.field_moved',
            default => 'forms.saved',
        }));

        return Response::redirect(Url::admin('forms', $form['id']) . ($action === 'save' || $action === '' ? '' : '#fields'));
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $form = Form::find($this->db(), (int) $params['id']);
        Form::delete($this->db(), (int) $params['id']);
        if ($form !== null) {
            Activity::record($this->db(), 'form', 'deleted', (int) $form['id'], (string) $form['name']);
        }
        $this->container->get('session')->set('flash', t('forms.deleted'));

        return Response::redirect(Url::admin('forms'));
    }

    /**
     * SEND A TEST (O-77): what a message to this form would send, with each field's label as its
     * value, to the address saved, signed as a real one is but marked form.test. Said in the
     * flash: taken, or why not.
     *
     * @param array<string, string> $params
     */
    public function testWebhook(Request $request, string $locale, array $params): Response
    {
        $form = Form::find($this->db(), (int) $params['id']);
        if ($form === null) {
            return Response::admin(e(t('forms.not_found')), 404);
        }
        $session = $this->container->get('session');
        if ($form['settings']['webhook'] === '') {
            $session->set('flash', t('forms.webhook_none'));
            $session->set('flash_kind', 'warning');
        } else {
            $answers = array_map(static fn (array $field): array => ['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'value' => $field['label']], $form['fields']);
            $failed = FormWebhook::send($this->container, $form, FormWebhook::payload($form, $answers, null, gmdate('Y-m-d H:i:s'), FormWebhook::TEST_EVENT));
            $session->set('flash', $failed === '' ? t('forms.webhook_test_sent') : t('forms.webhook_test_failed', ['reason' => $failed]));
            $session->set('flash_kind', $failed === '' ? 'success' : 'error');
        }

        return Response::redirect(Url::admin('forms', $form['id']) . '#webhook');
    }

    /**
     * @param array<string, string> $errors
     */
    private function list(array $errors, int $status): Response
    {
        return AdminView::render($this->container, __DIR__ . '/views', 'index', [
            'title' => t('forms.title'),
            'nav' => 'forms',
            'wide' => true,
            'forms' => Form::all($this->db()),
            'locales' => $this->container->get('locales'),
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $errors
     */
    private function screen(array $form, array $errors, int $status): Response
    {
        $labels = array_column($this->container->get('locales'), 'label', 'code');

        return AdminView::render($this->container, __DIR__ . '/views', 'edit', [
            'title' => t('forms.edit'),
            'nav' => 'forms',
            'styles' => ['admin-forms-builder.css'],
            'scripts' => ['form-builder.js'],
            'form' => $form,
            'language' => (string) ($labels[$form['locale']] ?? $form['locale']),
            'errors' => $errors,
            // Whether a message this form does not keep can reach the owner at all (D-269).
            'mailReady' => \App\Modules\Mailer\MailSettings::configured($this->db()),
            // The secret the receiver checks signatures with, shown to be copied there (O-77).
            'webhookSecret' => Secret::open((string) ($form['settings']['webhook_secret'] ?? ''), $this->appKey()),
        ], $status);
    }

    private function appKey(): string
    {
        return (string) $this->container->get('config')->get('app.key');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
