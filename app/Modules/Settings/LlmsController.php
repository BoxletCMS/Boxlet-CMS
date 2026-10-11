<?php

namespace App\Modules\Settings;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Modules\Pages\LlmsTxt;
use App\Support\Url;

/**
 * llms.txt's switch (PLAN.md D-151). On writes the file at once, off removes it; neither
 * touches an llms.txt the owner put there themselves.
 */
final class LlmsController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $on = $request->input('llms_txt') === '1';
        Settings::set($db, 'llms_txt', $on ? '1' : '0');
        LlmsTxt::publish($db, (string) (($this->container->get('config')->get('app', []))['public_path'] ?? ''));
        Activity::record($db, 'settings', $on ? 'llms_on' : 'llms_off', null, '');

        $session = $this->container->get('session');
        $session->set('flash', t($on ? 'llms.turned_on' : 'llms.turned_off'));
        $session->set('flash_kind', 'success');

        return Response::redirect(Url::admin('settings') . '#llms');
    }
}
