<?php

namespace App\Modules\Admin;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Support\Url;

/**
 * Route middleware: lets the request through only for a session that belongs to an
 * admin account that still exists; everyone else is sent to the login page.
 */
final class RequireAdmin
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(Request $request): ?Response
    {
        $id = $this->container->get('session')->get('admin_id');
        if (is_int($id) && $this->container->get('db')->one('SELECT id FROM admin WHERE id = ?', [$id]) !== null) {
            return null;
        }

        return Response::redirect(Url::admin('login'));
    }
}
