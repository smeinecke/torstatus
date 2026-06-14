<?php

declare(strict_types=1);

namespace TorStatus\Cache;

final class CacheFactory
{
    public static function create(string $backend, string $host, int $port = 0): CacheInterface
    {
        $normalized = strtolower(trim($backend));
        if ($normalized === '' || $normalized === 'memcache') {
            $normalized = 'memcached';
        }

        if ($normalized === 'none' || $normalized === 'null' || $normalized === 'off') {
            return new NullCache();
        }

        if ($normalized === 'redis' || $normalized === 'valkey') {
            return new RedisCache($host, $port > 0 ? $port : 6379);
        }

        if ($normalized === 'memcached') {
            return new MemcachedCache($host, $port > 0 ? $port : 11211);
        }

        throw new \InvalidArgumentException("Unsupported cache backend: $backend");
    }
}
