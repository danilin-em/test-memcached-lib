<?php

declare(strict_types=1);

namespace Memcached;

class Memcached
{
    public function get(string $key, $default = null): ?string
    {
        // TODO: Implement get() method.
        return null;
    }

    public function set(string $key, $value, int $ttl = null): void
    {
        // TODO: Implement set() method.
    }

    public function delete(string $key): void
    {
        // TODO: Implement delete() method.
    }
}