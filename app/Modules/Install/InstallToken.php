<?php

namespace App\Modules\Install;

/**
 * The install token: a secret written to storage/install-token.txt on the first
 * visit and asked for on the requirements step, so whoever installs proves they can read the
 * server's disk. The session keeps only its hash.
 */
final class InstallToken
{
    /** The token, created on the first visit. */
    public static function get(string $storage): string
    {
        $file = $storage . '/install-token.txt';
        if (!is_file($file)) {
            file_put_contents($file, bin2hex(random_bytes(16)) . "\n");
            chmod($file, 0600);
        }

        return trim((string) file_get_contents($file));
    }

    /**
     * Whether the session's progress was started with the token on the disk now.
     *
     * @param array<mixed> $state
     */
    public static function accepted(string $storage, array $state): bool
    {
        $file = $storage . '/install-token.txt';

        return is_string($state['token'] ?? null)
            && is_file($file)
            && hash_equals(hash('sha256', trim((string) file_get_contents($file))), $state['token']);
    }
}
