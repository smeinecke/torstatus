<?php

declare(strict_types=1);

namespace TorStatus\Cache;

final class RedisCache implements CacheInterface
{
    /** @var \Redis */
    private $client;

    public function __construct(string $host, int $port = 6379)
    {
        if (!class_exists(\Redis::class)) {
            throw new \RuntimeException('The PHP redis extension is not installed.');
        }

        $this->client = new \Redis();
        $this->client->connect($host, $port);
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($key);
        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl > 0) {
            return $this->client->setex($key, $ttl, $value) === true;
        }

        return $this->client->set($key, $value) === true;
    }
}
