<?php

namespace App\Modules\Update;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\Url;

/**
 * The owner's maintenance switch (PLAN.md D-021).
 *
 * A POST, so the router's CSRF check covers it, and behind RequireAdmin, so nobody else
 * can reach it. It only ever sets or clears the flag: the gate decides what that means.
 */
final class MaintenanceController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function toggle(Request $request, string $locale, array $params): Response
    {
        $maintenance = $this->container->get('maintenance');
        $on = $request->input('state') === 'on';

        if ($on) {
            $maintenance->turnOn(Maintenance::MANUAL);
        } else {
            // No reason given: the owner's switch clears whatever is there, including a
            // flag an interrupted update left behind. Slice 8 passes 'update' so that it
            // cannot clear one the owner set deliberately.
            $maintenance->turnOff();
        }

        Activity::record($this->container->get('db'), 'settings', $on ? 'maintenance_on' : 'maintenance_off', null, '');
        $this->container->get('session')->set('flash', $on ? t('maintenance.turned_on') : t('maintenance.turned_off'));

        // Back where they pressed it: site settings, or the site if they used the bar.
        // The switch moved off the dashboard with D-028.
        $from = $request->input('return');

        return Response::redirect($from === 'site' ? Url::page('') : Url::admin('settings'));
    }

    /**
     * The bar's "turn it off" link is a GET, because it sits in the site's own document
     * where a form would inherit the page's styling and a POST needs a token the page
     * does not carry. It confirms on site settings rather than acting — that is where the
     * switch is since D-028, so the link needs no change of its own.
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        return Response::redirect(Url::admin('settings'));
    }
}
