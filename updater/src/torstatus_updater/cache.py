"""Cache client adapters for memcached and Redis/Valkey."""

from __future__ import annotations

import logging
from typing import Any, Protocol

LOG = logging.getLogger(__name__)


class CacheClient(Protocol):
    """Small cache protocol used by the updater."""

    def get(self, key: str) -> str | None:
        """Return the cached value or *None* on miss."""
        ...

    def set(self, key: str, value: str, expire: int = 0) -> bool | None:
        """Store *value* under *key* with optional TTL."""
        ...


class NullCache:
    """No-op cache backend."""

    def get(self, key: str) -> str | None:
        """Return a guaranteed cache miss."""
        return None

    def set(self, key: str, value: str, expire: int = 0) -> bool:
        """Ignore writes."""
        return False


class MemcachedCache:
    """Memcached adapter with a Redis-like string API."""

    def __init__(self, host: str, port: int) -> None:
        """Create a memcached client."""
        import pymemcache.client.base

        self._client: Any = pymemcache.client.base.Client((host, port), encoding="utf-8")

    def get(self, key: str) -> str | None:
        """Read a value."""
        value = self._client.get(key)
        return value if isinstance(value, str) else None

    def set(self, key: str, value: str, expire: int = 0) -> bool | None:
        """Write a value."""
        return self._client.set(key, value, expire=expire)


class RedisCache:
    """Redis/Valkey adapter with a memcached-like string API."""

    def __init__(self, host: str, port: int) -> None:
        """Create a Redis-compatible client."""
        import redis

        self._client: Any = redis.Redis(host=host, port=port, decode_responses=True)

    def get(self, key: str) -> str | None:
        """Read a value."""
        value = self._client.get(key)
        return value if isinstance(value, str) else None

    def set(self, key: str, value: str, expire: int = 0) -> bool | None:
        """Write a value."""
        return self._client.set(key, value, ex=expire if expire > 0 else None)


def build_cache(config: dict[str, str]) -> CacheClient:
    """Build the configured cache client.

    When ``redis_uri`` is set (non-empty) a Redis/Valkey client is created;
    otherwise falls back to Memcached via ``memcached_host``.
    """
    redis_uri = config.get("redis_uri", "").strip()
    if redis_uri:
        # Parse simple URI like tcp://host:port or just host
        host = redis_uri
        port = 6379
        if "://" in host:
            host = host.split("://", 1)[1]
        if ":" in host:
            host, port_str = host.rsplit(":", 1)
            if port_str.isdigit():
                port = int(port_str)
        return RedisCache(host, port)

    memcached_host = config.get("memcached_host", "").strip() or "memcached"
    if memcached_host:
        return MemcachedCache(memcached_host, 11211)

    LOG.warning("No cache configured; using null cache")
    return NullCache()
