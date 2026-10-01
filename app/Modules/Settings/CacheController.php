<?php

namespace App\Modules\Settings;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Support\PageCache;
use App\Support\Url;

/**
 * The page cache's switch and its Clear now (PLAN.md D-053). Clearing needs nothing here:
 * every POST to the admin empties the cache on its way out (public/index.php), this one
 * included. It is done here as well so the screen that comes back counts none.
 */
final class CacheController
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
        $session = $this->container->get('session');
        if ($request->input('action') === 'clear') {
            PageCache::clear();
            $session->set('flash', t('cache.cleared'));
        } else {
            $on = $request->input('page_cache') === '1';
            Settings::set($db, 'page_cache', $on ? '1' : '0');
            PageCache::clear();
            Activity::record($db, 'settings', $on ? 'cache_on' : 'cache_off', null, '');
            $session->set('flash', t($on ? 'cache.turned_on' : 'cache.turned_off'));
        }
        $session->set('flash_kind', 'success');

        return Response::redirect(Url::admin('settings') . '#cache');
    }
}
