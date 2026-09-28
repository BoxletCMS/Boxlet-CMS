<?php

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Closure-based service factories. Each service is built once, on first get().
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new RuntimeException("Service not registered: {$id}");
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }
}
