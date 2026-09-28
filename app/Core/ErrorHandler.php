<?php

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Turns PHP errors into exceptions and renders uncaught ones: a readable trace when
 * debug is on, a plain 500 page otherwise. Deliberately independent of View and
 * Config, since either may be what failed.
 */
final class ErrorHandler
{
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    private function __construct(private readonly bool $debug)
    {
    }

    public static function register(bool $debug): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');

        $handler = new self($debug);
        set_error_handler([$handler, 'handleError']);
        set_exception_handler([$handler, 'handleException']);
        register_shutdown_function([$handler, 'handleShutdown']);
    }

    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }
        // Outside debug, a deprecation is logged rather than taking the site down.
        if (!$this->debug && ($level & (E_DEPRECATED | E_USER_DEPRECATED))) {
            error_log("Deprecated: {$message} in {$file}:{$line}");
            return true;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public function handleException(Throwable $e): void
    {
        error_log(sprintf('%s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $this->debug ? $this->debugPage($e) : $this->plainPage();
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && ($error['type'] & self::FATAL)) {
            $this->handleException(
                new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
            );
        }
    }

    private function plainPage(): string
    {
        return "<!doctype html>\n<html lang=\"en\"><head><meta charset=\"utf-8\">"
            . "<title>Server error</title></head><body>"
            . "<h1>Server error</h1><p>Something went wrong. Please try again later.</p>"
            . "</body></html>\n";
    }

    private function debugPage(Throwable $e): string
    {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sections = '';
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $sections .= '<h2>' . $h(get_class($current)) . '</h2>'
                . '<p><strong>' . $h($current->getMessage()) . '</strong></p>'
                . '<p>' . $h($current->getFile()) . ':' . $current->getLine() . '</p>'
                . '<pre>' . $h($current->getTraceAsString()) . '</pre>';
        }

        return "<!doctype html>\n<html lang=\"en\"><head><meta charset=\"utf-8\">"
            . '<title>' . $h(get_class($e)) . '</title></head><body>'
            . '<h1>Uncaught exception</h1>' . $sections
            . "</body></html>\n";
    }
}
