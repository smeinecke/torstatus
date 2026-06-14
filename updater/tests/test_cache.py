"""Tests for torstatus_updater.cache."""

from unittest.mock import MagicMock, patch

from torstatus_updater.cache import (
    CacheClient,
    MemcachedCache,
    NullCache,
    RedisCache,
    build_cache,
)


def test_null_cache_always_misses() -> None:
    cache = NullCache()
    assert cache.get("any-key") is None
    assert cache.set("any-key", "value") is False


def test_build_cache_prefers_redis_uri() -> None:
    cfg = {"redis_uri": "tcp://my-redis:6380", "memcached_host": "memcached"}
    with patch("redis.Redis") as mock_redis_cls:
        mock_client = MagicMock()
        mock_redis_cls.return_value = mock_client
        cache = build_cache(cfg)
        assert isinstance(cache, RedisCache)
        mock_redis_cls.assert_called_once_with(host="my-redis", port=6380, decode_responses=True)


def test_build_cache_falls_back_to_memcached() -> None:
    cfg = {"redis_uri": "", "memcached_host": "mymemcache"}
    with patch("pymemcache.client.base.Client") as mock_client_cls:
        cache = build_cache(cfg)
        assert isinstance(cache, MemcachedCache)
        mock_client_cls.assert_called_once_with(("mymemcache", 11211), encoding="utf-8")


def test_build_cache_memcached_default_host() -> None:
    cfg = {"redis_uri": ""}
    with patch("pymemcache.client.base.Client") as mock_client_cls:
        cache = build_cache(cfg)
        assert isinstance(cache, MemcachedCache)
        mock_client_cls.assert_called_once_with(("memcached", 11211), encoding="utf-8")


def test_build_cache_memcached_default_when_nothing_configured() -> None:
    cfg = {}
    with patch("pymemcache.client.base.Client") as mock_client_cls:
        cache = build_cache(cfg)
        assert isinstance(cache, MemcachedCache)
        mock_client_cls.assert_called_once_with(("memcached", 11211), encoding="utf-8")


def test_memcached_cache_get_set() -> None:
    with patch("pymemcache.client.base.Client") as mock_client_cls:
        mock_client = MagicMock()
        mock_client.get.return_value = "cached-value"
        mock_client_cls.return_value = mock_client
        cache = MemcachedCache("host", 11211)
        assert cache.get("key") == "cached-value"
        cache.set("key", "value", expire=60)
        mock_client.set.assert_called_once_with("key", "value", expire=60)


def test_memcached_cache_get_non_string_returns_none() -> None:
    with patch("pymemcache.client.base.Client") as mock_client_cls:
        mock_client = MagicMock()
        mock_client.get.return_value = b"bytes"
        mock_client_cls.return_value = mock_client
        cache = MemcachedCache("host", 11211)
        assert cache.get("key") is None


def test_redis_cache_get_set() -> None:
    with patch("redis.Redis") as mock_redis_cls:
        mock_client = MagicMock()
        mock_client.get.return_value = "cached-value"
        mock_redis_cls.return_value = mock_client
        cache = RedisCache("host", 6379)
        assert cache.get("key") == "cached-value"
        cache.set("key", "value", expire=120)
        mock_client.set.assert_called_once_with("key", "value", ex=120)


def test_redis_cache_set_no_expire() -> None:
    with patch("redis.Redis") as mock_redis_cls:
        mock_client = MagicMock()
        mock_redis_cls.return_value = mock_client
        cache = RedisCache("host", 6379)
        cache.set("key", "value", expire=0)
        mock_client.set.assert_called_once_with("key", "value", ex=None)


def test_redis_cache_get_non_string_returns_none() -> None:
    with patch("redis.Redis") as mock_redis_cls:
        mock_client = MagicMock()
        mock_client.get.return_value = None
        mock_redis_cls.return_value = mock_client
        cache = RedisCache("host", 6379)
        assert cache.get("key") is None
