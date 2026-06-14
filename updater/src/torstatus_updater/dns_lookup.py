"""Reverse DNS lookups with in-process LRU cache and optional Memcached."""

import logging
import re
import socket
from functools import lru_cache
from typing import Protocol

LOG = logging.getLogger(__name__)

IPV4_RE = re.compile(r"^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$")


class _MemcachedClient(Protocol):
    """Minimal duck-typing protocol for a cache client with get/set."""

    def get(self, key: str) -> str | None: ...
    def set(self, key: str, value: object, expire: int = 0) -> bool | None: ...  # noqa: V103


@lru_cache(maxsize=8192)
def _lookup_once(ip: str) -> str | None:
    """Perform a single reverse DNS lookup, returning *None* on failure."""
    try:
        hostname, _, _ = socket.gethostbyaddr(ip)
        return hostname
    except (TimeoutError, socket.herror, OSError):
        return None


def lookup(
    ip: str,
    memcached_client: _MemcachedClient | None = None,
    cache_expire: int = 86400,
) -> str:
    """Return the hostname for *ip*, falling back to the IP itself.

    An in-process LRU cache avoids repeated OS calls within the same
    process.  When *memcached_client* is supplied it is queried first
    and the result is written back on miss.
    """
    if not IPV4_RE.match(ip):
        return ip

    cache_key = f"torstatus_host_{ip}"

    # Check memcached first
    if memcached_client is not None:
        try:
            cached = memcached_client.get(cache_key)
        except Exception:
            LOG.exception("Memcached get failed for %s", ip)
            cached = None
        if cached is not None:
            LOG.debug("Memcached hit for %s -> %s", ip, cached)
            return cached

    # Fallback to OS lookup
    hostname = _lookup_once(ip)
    if hostname is None:
        hostname = ip

    # Write back to memcached
    if memcached_client is not None:
        try:
            memcached_client.set(cache_key, hostname, expire=cache_expire)
        except Exception:
            LOG.exception("Memcached set failed for %s", ip)

    return hostname
