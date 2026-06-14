"""Cache client adapters for memcached and Redis/Valkey."""

from __future__ import annotations

import logging
from typing import Any, Protocol

LOG = logging.getLogger(__name__)


class CacheClient(Protocol):
    """Small cache protocol used by the updater."""

    def get(self, key: str) -> str | None: ...

    def set(self, key: str, value: str, expire: int = 0) -> bool | None: ...  # noqa: V103


class NullCache:
    """No-op cache backend."""

    def get(self, key: str) -> str | None:
        """Return a guaranteed cache miss."""
        return None

    def set(self, key: str, value: str, expire: int = 0) -> bool:  # noqa: V103
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

    def set(self, key: str, value: str, expire: int = 0) -> bool | None:  # noqa: V103
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

    def set(self, key: str, value: str, expire: int = 0) -> bool | None:  # noqa: V103
        """Write a value."""
        return self._client.set(key, value, ex=expire if expire > 0 else None)


def build_cache(config: dict[str, str]) -> CacheClient:
    """Build the configured cache client.

    ``CACHE_*`` environment variables are resolved by ``parse_config`` when
    Docker's config file is used. Supported backends are ``memcached``,
    ``redis``, ``valkey`` and ``none``.
    """
    backend = config.get("cache_backend", "memcached").strip().lower() or "memcached"
    if backend == "memcache":
        backend = "memcached"

    if backend in {"none", "null", "off"}:
        return NullCache()

    host = config.get("cache_host") or config.get("memcached_host") or ("valkey" if backend in {"redis", "valkey"} else "memcached")
    default_port = 6379 if backend in {"redis", "valkey"} else 11211
    port = int(config.get("cache_port") or default_port)

    if backend == "memcached":
        return MemcachedCache(host, port)
    if backend in {"redis", "valkey"}:
        return RedisCache(host, port)

    LOG.warning("Unsupported cache backend %r; disabling cache", backend)
    return NullCache()
