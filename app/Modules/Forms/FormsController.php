<?php

namespace App\Modules\Forms;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
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
        ], $status);
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
