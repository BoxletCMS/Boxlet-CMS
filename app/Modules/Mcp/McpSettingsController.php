<?php

namespace App\Modules\Mcp;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\Url;

/**
 * AI ACCESS on the Settings screen (PLAN.md O-78): the address an AI client connects to, the keys,
 * a new one shown once, revoking one. Whether a key may publish is chosen as it is made (D-291).
 */
final class McpSettingsController
{
    private const NEW_KEY = 'mcp_new_key';

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * What the panel draws; the new key taken out of the session as it is drawn, so it is shown once.
     *
     * @return array{mcp: array{endpoint: string, keys: list<array<string, mixed>>, newKey: array{name: string, key: string}|null}}
     */
    public static function panel(Container $container): array
    {
        $session = $container->get('session');
        $new = $session->get(self::NEW_KEY);
        $session->remove(self::NEW_KEY);

        return ['mcp' => [
            // The route has no language: the main language's address is the bare path.
            'endpoint' => Url::canonical(Url::primaryLocale(), 'mcp'),
            'keys' => McpKeys::all($container->get('db')),
            'newKey' => is_array($new) && is_string($new['name'] ?? null) && is_string($new['key'] ?? null) ? ['name' => $new['name'], 'key' => $new['key']] : null,
        ]];
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, string $locale, array $params): Response
    {
        $name = trim($request->input('name'));
        $name = $name !== '' ? mb_substr($name, 0, 190) : t('mcp.key_default');
        // '0' reads, '1' writes drafts, '2' also publishes (D-291).
        $may = $request->input('write');
        $made = McpKeys::create($this->db(), $name, $may === '1' || $may === '2', (string) $this->container->get('config')->get('app.key'), $may === '2');
        Activity::record($this->db(), 'settings', 'mcp_key_created', $made['id'], $name);
        $this->container->get('session')->set(self::NEW_KEY, ['name' => $name, 'key' => $made['key']]);

        return Response::redirect(Url::admin('settings', 'ai', 'access'));
    }

    /**
     * @param array<string, string> $params
     */
    public function revoke(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $key = $this->db()->one('SELECT name FROM mcp_keys WHERE id = ?', [$id]);
        McpKeys::revoke($this->db(), $id);
        if ($key !== null) {
            Activity::record($this->db(), 'settings', 'mcp_key_revoked', $id, (string) $key['name']);
        }
        $session = $this->container->get('session');
        $session->set('flash', t('mcp.revoked'));

        return Response::redirect(Url::admin('settings', 'ai', 'access'));
    }

    /**
     * A revoked key off the list.
     *
     * @param array<string, string> $params
     */
    public function remove(Request $request, string $locale, array $params): Response
    {
        McpKeys::remove($this->db(), (int) $params['id']);

        return Response::redirect(Url::admin('settings', 'ai', 'access'));
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
