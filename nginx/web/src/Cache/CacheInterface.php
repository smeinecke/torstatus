<?php

declare(strict_types=1);

namespace TorStatus\Cache;

interface CacheInterface
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, int $ttl = 0): bool;
}
