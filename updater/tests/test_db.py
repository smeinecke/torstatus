"""Tests for torstatus_updater.db."""

from unittest.mock import MagicMock, call, patch

from torstatus_updater.db import Database


def _mock_cursor(rows=None):
    cur = MagicMock()
    cur.fetchone.return_value = rows
    cur.fetchall.return_value = rows or []
    return cur


@patch("torstatus_updater.db.pymysql.connect")
def test_check_installed_true(mock_connect) -> None:
    mock_conn = MagicMock()
    mock_conn.cursor.return_value.__enter__ = MagicMock(return_value=_mock_cursor((5,)))
    mock_conn.cursor.return_value.__exit__ = MagicMock(return_value=False)
    mock_connect.return_value = mock_conn

    db = Database("host", "user", "pass", "db")
    assert db.check_installed() is True


@patch("torstatus_updater.db.pymysql.connect")
def test_active_tables_flips(mock_connect) -> None:
    mock_conn = MagicMock()
    # First call returns table 1 active -> next should be 2
    mock_conn.cursor.return_value.__enter__ = MagicMock(return_value=_mock_cursor(("NetworkStatus1", "Descriptor1")))
    mock_conn.cursor.return_value.__exit__ = MagicMock(return_value=False)
    mock_connect.return_value = mock_conn

    db = Database("host", "user", "pass", "db")
    tbl, desc, ns, ora, bw = db.active_tables()
    assert tbl == 2
    assert desc == "Descriptor2"
    assert ns == "NetworkStatus2"


@patch("torstatus_updater.db.pymysql.connect")
def test_truncate_staging(mock_connect) -> None:
    mock_conn = MagicMock()
    cur = _mock_cursor()
    mock_conn.cursor.return_value.__enter__ = MagicMock(return_value=cur)
    mock_conn.cursor.return_value.__exit__ = MagicMock(return_value=False)
    mock_connect.return_value = mock_conn

    db = Database("host", "user", "pass", "db")
    db.truncate_staging(1)

    calls = [c.args[0] for c in cur.execute.call_args_list]
    assert "TRUNCATE TABLE Bandwidth1" in calls
    assert "TRUNCATE TABLE Descriptor1" in calls
    assert "TRUNCATE TABLE ORAddresses1" in calls
    assert "TRUNCATE TABLE NetworkStatus1" in calls
