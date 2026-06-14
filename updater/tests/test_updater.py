"""Tests for torstatus_updater.updater."""

from unittest.mock import MagicMock

import pymysql.cursors

from torstatus_updater.updater import (
    _fetch_extra_info,
    _process_history,
    _update_descriptors_indexed,
    update_hostnames,
    update_network_status,
)


def test_process_history() -> None:
    import re

    m = re.match(
        r"^read-history\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\s+\((\d+)\s+s\)\s+(.+)$",
        "read-history 2024-01-01 00:00:00 (900 s) 1,2,3,4,5",
    )
    assert m is not None
    hist, ts_str, inc, ser = _process_history(m)
    assert ts_str == "2024-01-01 00:00:00"
    assert inc == 900
    # 5 values reversed -> last entry is oldest
    assert hist.count(":") == 5


def test_update_descriptors_indexed_minimal() -> None:
    """Feed a minimal single-router descriptor and assert one insert."""
    lines = [
        "router TestRelay 1.2.3.4 9001 0 9030",
        "bandwidth 100 200 150",
        "platform Tor 0.4.8.0",
        "published 2024-01-01 00:00:00",
        "fingerprint ABCD ABCD ABCD ABCD ABCD ABCD ABCD ABCD ABCD ABCD",
        "uptime 3600",
        "onion-key",
        "-----BEGIN RSA PUBLIC KEY-----",
        "MIIBCgK...",
        "-----END RSA PUBLIC KEY-----",
        "signing-key",
        "-----BEGIN RSA PUBLIC KEY-----",
        "MIIBCgK...",
        "-----END RSA PUBLIC KEY-----",
        "contact test@example.com",
        "family $1234 $5678",
        "accept *:80",
        "reject *:*",
        "router-signature",
        "-----BEGIN SIGNATURE-----",
        "sigdata",
        "-----END SIGNATURE-----",
    ]
    cursor = MagicMock(spec=pymysql.cursors.Cursor)
    cursor.lastrowid = 42
    tor = MagicMock()

    count = _update_descriptors_indexed(tor, lines, cursor, "INSERT DESC", "INSERT BW", "INSERT OR")
    assert count == 1
    # descriptor + bandwidth (no or-address in this minimal descriptor)
    assert cursor.execute.call_count == 2


def test_update_descriptors_ipv6_or_address() -> None:
    """IPv6 OR addresses are normalized and inserted in ORAddresses."""
    lines = [
        "router TestRelay 1.2.3.4 9001 0 9030",
        "or-address [2001:0db8::1]:9001",
        "bandwidth 100 200 150",
        "published 2024-01-01 00:00:00",
        "fingerprint ABCD ABCD ABCD ABCD ABCD ABCD ABCD ABCD ABCD ABCD",
        "router-signature",
        "-----BEGIN SIGNATURE-----",
        "sigdata",
        "-----END SIGNATURE-----",
    ]
    cursor = MagicMock(spec=pymysql.cursors.Cursor)
    cursor.lastrowid = 42
    tor = MagicMock()

    _update_descriptors_indexed(tor, lines, cursor, "INSERT DESC", "INSERT BW", "INSERT OR")

    assert cursor.execute.call_args_list[-1].args == ("INSERT OR", (42, "2001:db8::1", 9001))


def test_update_network_status_minimal() -> None:
    lines = [
        "r TestRelay AAAAbbbb /abcd 2024-01-01 00:00:00 1.2.3.4 9001 9030",
        "s Fast Guard Running Stable Valid",
        "250 OK",
    ]
    tor = MagicMock()
    tor.get_info_lines.return_value = lines
    db = MagicMock()
    cursor = MagicMock(spec=pymysql.cursors.Cursor)
    db.cursor.return_value = cursor
    ip_list = []

    update_network_status(tor, db, 1, [[0, 4294967295, "US"]], 1)
    assert cursor.execute.called


def test_fetch_extra_info() -> None:
    lines = [
        "extra-info TestRelay abcd",
        "read-history 2024-01-01 00:00:00 (900 s) 10,20,30",
        "write-history 2024-01-01 00:00:00 (900 s) 5,15,25",
    ]
    tor = MagicMock()
    tor.get_info_lines.return_value = lines
    current = {"Digest": "abcd extra"}
    _fetch_extra_info(tor, current)
    assert "read" in current
    assert "write" in current
    assert current["ReadHistoryINC"] == 900
    assert current["WriteHistoryINC"] == 900


def test_update_hostnames() -> None:
    db = MagicMock()
    cursor = MagicMock(spec=pymysql.cursors.Cursor)
    cursor.fetchall.return_value = [
        ("FP1", "1.2.3.4"),
        ("FP2", "5.6.7.8"),
    ]
    cursor.__enter__ = MagicMock(return_value=cursor)
    cursor.__exit__ = MagicMock(return_value=False)
    db.cursor.return_value = cursor

    update_hostnames(db, 1, None, 2)
    assert cursor.execute.call_count >= 2  # SELECT + 2 UPDATEs
