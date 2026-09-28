<?php

namespace App\Core;

final class Response
{
    /**
     * A file on disk sent as the body, read out in pieces rather than held in memory: a
     * download may be a hundred megabytes (PLAN.md D-126), and a string of that size is
     * a request a shared host kills.
     */
    public ?string $file = null;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    /**
     * A file for the visitor to SAVE, never to open (D-126): an attachment, of the type it
     * was stored as, which the browser may not second-guess, and which could not run
     * anything even if it did — a sandbox with nothing allowed.
     */
    public static function download(string $path, string $mime, string $name): self
    {
        $ascii = (string) preg_replace('~[^A-Za-z0-9._-]+~', '-', $name);
        $response = new self('', 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
        $response->file = $path;

        return $response;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * HTML for admin and installer pages: never cached, never framed, and a CSP that
     * allows no inline scripts or styles (SPEC §6).
     */
    public static function admin(string $body, int $status = 200): self
    {
        $response = self::html($body, $status);
        $response->headers += [
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; form-action 'self'; "
                . "frame-ancestors 'none'; base-uri 'none'",
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Cache-Control' => 'no-store',
        ];

        return $response;
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if ($this->file !== null) {
            // Whatever a buffer holds would go out first and corrupt the file, and a buffer
            // the size of the file is the memory this exists to avoid.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $handle = fopen($this->file, 'rb');
            if ($handle !== false) {
                while (!feof($handle)) {
                    echo (string) fread($handle, 1048576);
                    flush();
                }
                fclose($handle);
            }

            return;
        }
        echo $this->body;
    }
}
