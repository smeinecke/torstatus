<?php

declare(strict_types=1);

namespace TorStatus\Cache;

final class MemcachedCache implements CacheInterface
{
    /** @var \Memcached */
    private $client;

    public function __construct(string $host, int $port = 11211)
    {
        if (!class_exists(\Memcached::class)) {
            throw new \RuntimeException('The PHP memcached extension is not installed.');
        }

        $this->client = new \Memcached();
        $this->client->addServer($host, $port);
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($key);
        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        return $this->client->set($key, $value, $ttl);
    }
}
