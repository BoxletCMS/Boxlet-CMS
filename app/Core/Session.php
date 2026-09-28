<?php

namespace App\Core;

/**
 * Session data in $_SESSION. start() opens the native session with hardened cookie
 * settings (SPEC §6); constructed directly, as tests do, it works on $_SESSION alone.
 */
final class Session
{
    /**
     * @param string $savePath directory for session files, '' for PHP's default
     */
    public static function start(string $savePath, bool $secure): self
    {
        if (session_status() === PHP_SESSION_NONE) {
            if ($savePath !== '') {
                if (!is_dir($savePath)) {
                    mkdir($savePath, 0700, true);
                }
                session_save_path($savePath);
                // Debian-style hosts only garbage-collect PHP's default path, by cron.
                ini_set('session.gc_probability', '1');
                ini_set('session.gc_divisor', '100');
            }
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_name('boxlet_session');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }

        return new self();
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * New session id and CSRF token, keeping the data. Call on every privilege change.
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION['_csrf']);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function csrfToken(): string
    {
        $token = $_SESSION['_csrf'] ?? null;
        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['_csrf'] = $token;
        }

        return $token;
    }

    public function validCsrf(mixed $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? null;

        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
