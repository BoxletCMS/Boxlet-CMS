<?php

namespace App\Core;

/**
 * Checks over HTTP that URL rewriting reaches the front controller. For the installer,
 * where the user is waiting and a failure means something. Never called during page
 * render: a network call to the site itself can hang, is blocked by some hosts and
 * gives wrong answers behind auth or a proxy.
 */
final class RewriteCheck
{
    public const PROBE_PATH = '/_boxlet/rewrite-check';
    public const TOKEN = 'boxlet-rewrite-ok';

    /**
     * True only if $baseUrl + PROBE_PATH is answered by Boxlet itself. A server 404,
     * the rewriting-required page, a redirect or a connection failure all mean false.
     */
    public static function works(string $baseUrl, float $timeoutSeconds = 5.0): bool
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);

        // A connection failure is an expected answer here, not an error to report.
        set_error_handler(static fn (): bool => true);
        try {
            $body = file_get_contents(rtrim($baseUrl, '/') . self::PROBE_PATH, false, $context);
        } finally {
            restore_error_handler();
        }

        return is_string($body) && trim($body) === self::TOKEN;
    }

    /**
     * The answer to the probe. public/index.php sends it before bootstrapping, so it
     * works on a site that has no database yet.
     */
    public static function response(): Response
    {
        return new Response(self::TOKEN, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
