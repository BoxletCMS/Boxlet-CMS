<?php

namespace App\Core;

/**
 * Loads every config/*.php file, keyed by file name. get('app.debug') reads
 * config/app.php => ['debug' => ...].
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(string $directory)
    {
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $this->items[basename($file, '.php')] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
