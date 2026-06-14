"""Tests for torstatus_updater.dns_lookup."""

import socket
from unittest.mock import MagicMock, patch

from torstatus_updater.dns_lookup import _lookup_once, lookup


def setup_module() -> None:
    _lookup_once.cache_clear()


def teardown_function() -> None:
    _lookup_once.cache_clear()


def test_non_ip_returns_unchanged() -> None:
    assert lookup("example.com") == "example.com"


def test_ipv6_lookup_failure_fallback() -> None:
    with patch("socket.gethostbyaddr", side_effect=socket.herror):
        assert lookup("2001:db8::1") == "2001:db8::1"


def test_lookup_failure_fallback() -> None:
    _lookup_once.cache_clear()
    with patch("socket.gethostbyaddr", side_effect=socket.herror):
        assert lookup("1.2.3.4") == "1.2.3.4"


def test_lookup_success() -> None:
    _lookup_once.cache_clear()
    with patch("socket.gethostbyaddr", return_value=("host.example.com", [], [])):
        assert lookup("1.2.3.4") == "host.example.com"


def test_cache_hit() -> None:
    mock_client = MagicMock()
    mock_client.get.return_value = "cached.example.com"
    assert lookup("9.9.9.9", cache_client=mock_client) == "cached.example.com"
    mock_client.get.assert_called_once_with("torstatus_host_9.9.9.9")


def test_cache_miss_then_lookup() -> None:
    _lookup_once.cache_clear()
    mock_client = MagicMock()
    mock_client.get.return_value = None
    with patch("socket.gethostbyaddr", return_value=("host.example.com", [], [])):
        assert lookup("9.9.9.9", cache_client=mock_client) == "host.example.com"
    mock_client.set.assert_called_once_with("torstatus_host_9.9.9.9", "host.example.com", expire=86400)
