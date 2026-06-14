<?php

declare(strict_types=1);

namespace TorStatus\Cache;

final class NullCache implements CacheInterface
{
    public function get(string $key): ?string
    {
        return null;
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        return false;
    }
}
