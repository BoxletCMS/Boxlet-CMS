<?php

namespace App\Modules\Media;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\Url;

/**
 * Making every picture's sizes again, from the Media screen (PLAN.md D-048): Start owes
 * every picture a remake; each Continue does what fits in one request and comes back with
 * how many are left. auto-continue.js presses Continue by itself while any are left, so with
 * a script it runs to the end on its own; without one the owner presses it.
 */
final class MediaRemakeController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function start(Request $request, string $locale, array $params): Response
    {
        $count = $this->remake()->start();
        Activity::record($this->container->get('db'), 'media', 'remade', null, (string) $count);
        $this->container->get('session')->set('flash', t('media.remake_started', ['count' => (string) $count]));

        return Response::redirect(Url::admin('media') . '#remake');
    }

    /**
     * @param array<string, string> $params
     */
    public function step(Request $request, string $locale, array $params): Response
    {
        $result = $this->remake()->step(MediaController::budget(microtime(true)));
        $this->container->get('session')->set('flash', $result['left'] === 0
            ? t('media.remake_done')
            : t('media.remake_progress', ['left' => (string) $result['left']]));

        return Response::redirect(Url::admin('media') . '#remake');
    }

    private function remake(): MediaRemake
    {
        return $this->container->get('media_remake');
    }
}
