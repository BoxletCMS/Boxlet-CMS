<?php

namespace App\Core;

final class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers lower-case names
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $basePath,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly string $ip = '',
        public readonly bool $https = false,
        /**
         * Uploaded files, as PHP hands them over. The admin's first multipart form is the
         * picture library; before it, nothing here needed $_FILES.
         *
         * @var array<string, mixed>
         */
        public readonly array $files = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        // URL rewriting is required, so the path always comes from the request URI.
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $basePath = self::basePath((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'), $path);
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath));
        }
        $path = rawurldecode($path);

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with((string) $name, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = (string) $value;
            }
        }
        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $name) {
            if (isset($_SERVER[$name])) {
                $headers[strtolower(str_replace('_', '-', $name))] = (string) $_SERVER[$name];
            }
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? 'off')) !== 'off' && ($_SERVER['HTTPS'] ?? '') !== ''
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            '/' . ltrim($path, '/'),
            $basePath,
            $_GET,
            $_POST,
            $headers,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            $https,
            $_FILES,
        );
    }

    /**
     * The part of the address in front of every Boxlet path: normally the directory the
     * front controller is in, as SCRIPT_NAME gives it.
     *
     * NOT WHEN BOXLET SITS IN THE WEB ROOT ITSELF (cPanel's public_html, PLAN.md D-138).
     * There the .htaccess beside public/ hands every request to public/ without it being in
     * the address, so SCRIPT_NAME says /public/index.php for an address of /about, and every
     * link would have come out as /public/about. The base is therefore the longest part of
     * the script's directory that the address really starts with.
     */
    public static function basePath(string $scriptName, string $path): string
    {
        $base = self::directory($scriptName);
        while ($base !== '' && $path !== $base && !str_starts_with($path, $base . '/')) {
            $base = self::directory($base);
        }

        return $base;
    }

    /** The directory of an address path, '' for the top: never '/' or '.'. */
    private static function directory(string $path): string
    {
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $directory === '.' ? '' : $directory;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * A string field from the POST body. Missing fields and arrays read as ''.
     */
    public function input(string $key): string
    {
        $value = $this->body[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
