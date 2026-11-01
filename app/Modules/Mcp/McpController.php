<?php

namespace App\Modules\Mcp;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Support\Url;

/**
 * POST /mcp, the MCP endpoint (PLAN.md O-78). Guards in the order a request meets them:
 *
 * 1. Its Origin, where it has one, is this site's — the transport requires it, against a web
 *    page in someone's browser reaching a server through DNS rebinding (403).
 * 2. A key, as `Authorization: Bearer bxk_…`, that is not revoked (401). No session, no cookie:
 *    the route is exempt from the session's CSRF check as a visitor's form is, and stands
 *    guard itself, by the key.
 * 3. The key's limit, so many calls a minute (429).
 *
 * Then McpServer answers the message. A GET or DELETE, which earlier revisions used for streams
 * and sessions Boxlet has neither of, is 405.
 */
final class McpController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, string $locale, array $params): Response
    {
        $origin = $request->header('origin');
        if ($origin !== null && $origin !== '' && rtrim($origin, '/') !== rtrim(Url::withOrigin(''), '/')) {
            return self::json(403, self::error('Forbidden: this Origin may not call this server.'));
        }
        $authorization = (string) $request->header('authorization', '');
        $given = preg_match('~^Bearer\s+(\S+)$~i', $authorization, $match) === 1 ? $match[1] : '';
        $key = $given === '' ? null : McpKeys::find($this->container->get('db'), $given, (string) $this->container->get('config')->get('app.key'));
        if ($key === null) {
            $response = self::json(401, self::error('A key is needed: Authorization: Bearer and a key made in Settings → AI access.'));
            $response->headers['WWW-Authenticate'] = 'Bearer realm="Boxlet"';

            return $response;
        }
        if (!McpKeys::allow($this->container->get('db'), $key['id'])) {
            $response = self::json(429, self::error('Too many calls: at most ' . McpKeys::PER_MINUTE . ' a minute for one key.'));
            $response->headers['Retry-After'] = '60';

            return $response;
        }

        $headers = [];
        foreach (['mcp-protocol-version', 'mcp-method', 'mcp-name'] as $name) {
            $value = $request->header($name);
            if ($value !== null) {
                $headers[$name] = $value;
            }
        }
        $server = new McpServer(new McpTools($this->container), (string) $this->container->get('version'));
        [$status, $answer] = $server->handle($request->body === [] ? null : $request->body, $headers, $key);

        return $answer === null ? new Response('', $status, ['Cache-Control' => 'no-store']) : self::json($status, $answer);
    }

    /**
     * GET, DELETE: no stream to open and no session to end in this revision.
     *
     * @param array<string, string> $params
     */
    public function refuse(Request $request, string $locale, array $params): Response
    {
        $response = self::json(405, self::error('Method Not Allowed: POST one JSON-RPC message to this address.'));
        $response->headers['Allow'] = 'POST';

        return $response;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function json(int $status, array $body): Response
    {
        return new Response((string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A JSON-RPC error with no id, as the transport answers a request it would not read.
     *
     * @return array<string, mixed>
     */
    private static function error(string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => $message]];
    }
}
