"""Reverse DNS lookups with in-process LRU cache and optional shared cache."""

from __future__ import annotations

import ipaddress
import logging
import socket
from functools import lru_cache

from .cache import CacheClient

LOG = logging.getLogger(__name__)


@lru_cache(maxsize=8192)
def _lookup_once(ip: str) -> str | None:
    """Perform a single reverse DNS lookup, returning *None* on failure."""
    try:
        hostname, _, _ = socket.gethostbyaddr(ip)
        return hostname
    except (TimeoutError, socket.herror, OSError):
        return None


def _normalize_ip(ip: str) -> str | None:
    try:
        return str(ipaddress.ip_address(ip.strip("[]")))
    except ValueError:
        return None


def lookup(
    ip: str,
    cache_client: CacheClient | None = None,
    cache_expire: int = 86400,
) -> str:
    """Return the hostname for *ip*, falling back to the normalized IP itself."""
    normalized = _normalize_ip(ip)
    if normalized is None:
        return ip

    cache_key = f"torstatus_host_{normalized}"

    if cache_client is not None:
        try:
            cached = cache_client.get(cache_key)
        except Exception:
            LOG.exception("Cache get failed for %s", normalized)
            cached = None
        if cached is not None:
            LOG.debug("Cache hit for %s -> %s", normalized, cached)
            return cached

    hostname = _lookup_once(normalized) or normalized

    if cache_client is not None:
        try:
            cache_client.set(cache_key, hostname, expire=cache_expire)
        except Exception:
            LOG.exception("Cache set failed for %s", normalized)

    return hostname
