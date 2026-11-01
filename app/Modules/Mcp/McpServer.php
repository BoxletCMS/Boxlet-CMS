<?php

namespace App\Modules\Mcp;

/**
 * THE MODEL CONTEXT PROTOCOL, AS BOXLET SPEAKS IT (PLAN.md O-78): JSON-RPC over the Streamable
 * HTTP transport, one POST per message, a single JSON answer each. No session, no stream, no
 * request of the server's own: what a shared host can serve.
 *
 * DUAL-ERA, as the 2026-07-28 revision describes it. A MODERN request carries its protocol
 * version in params._meta and mirrors it, the method and a tool's name into headers, which
 * must agree with the body (HeaderMismatch, -32020) — so a gateway routing on a header cannot
 * be told one thing while Boxlet does another. A LEGACY client (2025-03-26 to 2025-11-25) opens
 * with `initialize`, which is answered; Boxlet issues no session id, which those revisions
 * allow, so each of its requests stands alone too.
 *
 * What the tools do is McpTools'; this file is only the protocol around them.
 *
 * @phpstan-import-type Key from McpKeys
 */
final class McpServer
{
    public const MODERN = ['2026-07-28'];
    public const LEGACY = ['2025-11-25', '2025-06-18', '2025-03-26'];
    private const META_VERSION = 'io.modelcontextprotocol/protocolVersion';

    public function __construct(private readonly McpTools $tools, private readonly string $version)
    {
    }

    /**
     * One message, answered: the HTTP status and the JSON body, or null for 202 with none.
     *
     * @param array<string, string> $headers lower-cased names
     * @param Key $key
     * @return array{int, array<string, mixed>|null}
     */
    public function handle(mixed $message, array $headers, array $key): array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0' || !is_string($message['method'] ?? null)) {
            return [400, self::error(null, -32600, 'Invalid Request: one JSON-RPC 2.0 request or notification per POST.')];
        }
        $method = $message['method'];
        // A notification is taken and answered with nothing; none asks Boxlet to do anything.
        if (!array_key_exists('id', $message)) {
            return [202, null];
        }
        $id = $message['id'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        $meta = is_array($params['_meta'] ?? null) ? $params['_meta'] : [];
        $headerVersion = $headers['mcp-protocol-version'] ?? null;

        if ($method === 'initialize') {
            $asked = is_string($params['protocolVersion'] ?? null) ? $params['protocolVersion'] : '';

            return [200, self::result($id, [
                'protocolVersion' => in_array($asked, self::LEGACY, true) ? $asked : self::LEGACY[0],
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => $this->info(),
                'instructions' => self::INSTRUCTIONS,
            ])];
        }

        $modern = is_string($meta[self::META_VERSION] ?? null);
        if ($modern) {
            $version = (string) $meta[self::META_VERSION];
            $mismatch = match (true) {
                $headerVersion === null => 'MCP-Protocol-Version header missing',
                $headerVersion !== $version => "MCP-Protocol-Version header '{$headerVersion}' does not match the body's '{$version}'",
                ($headers['mcp-method'] ?? null) !== $method => 'Mcp-Method header missing or not the body\'s method',
                in_array($method, ['tools/call', 'resources/read', 'prompts/get'], true) && self::decoded($headers['mcp-name'] ?? null) !== (string) ($params['name'] ?? $params['uri'] ?? '') => 'Mcp-Name header missing or not the body\'s name',
                default => null,
            };
            if ($mismatch !== null) {
                return [400, self::error($id, -32020, 'Header mismatch: ' . $mismatch)];
            }
            if (!in_array($version, self::MODERN, true) && !in_array($version, self::LEGACY, true)) {
                return [400, self::error($id, -32022, 'Unsupported protocol version', ['supported' => array_merge(self::MODERN, self::LEGACY), 'requested' => $version])];
            }
        } elseif ($headerVersion !== null && !in_array($headerVersion, self::LEGACY, true)) {
            // A legacy request names a legacy version, or none (2025-03-26 had no header).
            return [400, in_array($headerVersion, self::MODERN, true)
                ? self::error($id, -32020, 'Header mismatch: io.modelcontextprotocol/protocolVersion missing from params._meta')
                : self::error($id, -32022, 'Unsupported protocol version', ['supported' => array_merge(self::MODERN, self::LEGACY), 'requested' => $headerVersion])];
        }
        $complete = static fn (array $result): array => $modern ? ['resultType' => 'complete'] + $result : $result;
        // CACHING HINTS, which a modern client requires on discover and on a list (the
        // caching utility: ttlMs and cacheScope are required fields of DiscoverResult and
        // ListToolsResult). Discover is the same for every caller; the tools follow the key and
        // the owner's publish setting, so they are private, and fresh for a minute.
        $cached = static fn (array $result, int $ttlMs, string $scope): array => $modern ? $complete($result) + ['ttlMs' => $ttlMs, 'cacheScope' => $scope] : $result;

        switch ($method) {
            case 'server/discover':
                return [200, self::result($id, $cached([
                    'supportedVersions' => array_merge(self::MODERN, self::LEGACY),
                    'capabilities' => ['tools' => ['listChanged' => false]],
                    '_meta' => ['io.modelcontextprotocol/serverInfo' => $this->info()],
                    'instructions' => self::INSTRUCTIONS,
                ], 3600000, 'public'))];
            case 'ping':
                return [200, self::result($id, $complete([]))];
            case 'tools/list':
                return [200, self::result($id, $cached(['tools' => $this->tools->definitions($key)], 60000, 'private'))];
            case 'tools/call':
                $name = is_string($params['name'] ?? null) ? $params['name'] : '';
                $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
                if (!$this->tools->offers($name, $key)) {
                    return [200, self::error($id, -32602, 'Unknown tool: ' . $name)];
                }

                return [200, self::result($id, $complete($this->tools->call($name, $arguments, $key)))];
            default:
                return [$modern ? 404 : 200, self::error($id, -32601, 'Method not found: ' . $method)];
        }
    }

    /** What a model is told about this server before it calls anything. */
    private const INSTRUCTIONS = 'Boxlet is the CMS of one website. Start with get_site. A page is a document of sections and blocks: '
        . 'read one with get_page, change the document and give the whole of it back to update_page, which saves a draft the owner '
        . 'reviews and publishes; create_page makes a new one, unpublished. list_block_types says what each block holds and in what '
        . 'form. Pictures are media ids from list_media or add_media_from_url. A design set (get_design, get_design_set_schema, '
        . 'create_design_set) is kept for the owner to try in Appearance, never applied to the site by this server.';

    /**
     * @return array{name: string, title: string, version: string}
     */
    private function info(): array
    {
        return ['name' => 'boxlet', 'title' => 'Boxlet', 'version' => $this->version];
    }

    /** A header value, its Base64 sentinel form decoded (=?base64?...?=). */
    private static function decoded(?string $value): ?string
    {
        if ($value !== null && preg_match('~^=\?base64\?(.*)\?=$~', $value, $match) === 1) {
            $decoded = base64_decode($match[1], true);

            return $decoded === false ? null : $decoded;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private static function result(mixed $id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result === [] ? new \stdClass() : $result];
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>
     */
    private static function error(mixed $id, int $code, string $message, ?array $data = null): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message] + ($data === null ? [] : ['data' => $data])];
    }
}
