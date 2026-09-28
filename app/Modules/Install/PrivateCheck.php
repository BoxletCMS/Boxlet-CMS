<?php

namespace App\Modules\Install;

/**
 * Checks over HTTP that nothing beside public/ can be downloaded (PLAN.md D-138): above all
 * .env, which holds the database password once the installer has written it.
 *
 * Where the domain points at public/, the folder above it is outside the web root and this
 * holds by itself. Where Boxlet sits in the web root itself (cPanel's public_html), it holds
 * only while the web server reads the .htaccess beside public/, and a host that ignores
 * .htaccess files would serve everything. So it is checked rather than assumed: a file with
 * a random name is put beside public/ and asked for over HTTP, and the installer goes no
 * further if it comes back.
 *
 * Asked for at two places, the site's base and, when the installer was reached as
 * /public/install.php, the address above it, which is where that folder would be served.
 *
 * A connection that fails reads as hidden. On its own that would be a check that passes by
 * not looking; it is safe here because the rewrite check beside it needs a real answer from
 * the same site, and blocks the installer when there is none.
 */
final class PrivateCheck
{
    public static function hidden(string $root, string $baseUrl, string $basePath, float $timeoutSeconds = 5.0): bool
    {
        $name = 'boxlet-private-check-' . bin2hex(random_bytes(8)) . '.txt';
        $token = bin2hex(random_bytes(16));
        if (@file_put_contents($root . '/' . $name, $token) === false) {
            // Nothing to ask for, so nothing is known: the settings file check beside this
            // one already reports a folder the installer cannot write to.
            return false;
        }

        $bases = [rtrim($baseUrl, '/')];
        if (str_ends_with($basePath, '/public')) {
            $bases[] = substr(rtrim($baseUrl, '/'), 0, -strlen('/public'));
        }
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);

        set_error_handler(static fn (): bool => true);
        try {
            foreach ($bases as $base) {
                $body = file_get_contents($base . '/' . $name, false, $context);
                if (is_string($body) && trim($body) === $token) {
                    return false;
                }
            }

            return true;
        } finally {
            restore_error_handler();
            @unlink($root . '/' . $name);
        }
    }
}
