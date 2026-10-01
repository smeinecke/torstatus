"""Tests for torstatus_updater.tor_client."""

import socket
from unittest.mock import MagicMock, patch

from torstatus_updater.tor_client import TorClient


@patch("torstatus_updater.tor_client.Controller.from_port")
def test_connect_authenticate(mock_from_port) -> None:
    mock_ctrl = MagicMock()
    mock_from_port.return_value = mock_ctrl

    client = TorClient("127.0.0.1", 9051, password="secret")
    client.connect()

    mock_from_port.assert_called_once_with(address="127.0.0.1", port=9051)
    mock_ctrl.authenticate.assert_called_once_with(password="secret")
    mock_ctrl.signal.assert_called_once_with("ACTIVE")
    client.close()
    mock_ctrl.close.assert_called_once()


@patch("torstatus_updater.tor_client.Controller.from_port")
@patch("torstatus_updater.tor_client.socket.getaddrinfo")
def test_connect_resolves_hostname(mock_getaddrinfo, mock_from_port) -> None:
    """stem's from_port rejects hostnames — connect() must resolve them."""
    mock_ctrl = MagicMock()
    mock_from_port.return_value = mock_ctrl
    mock_getaddrinfo.return_value = [
        (socket.AF_INET, socket.SOCK_STREAM, 6, "", ("10.11.12.13", 9051))
    ]

    client = TorClient("tor.internal", 9051)
    client.connect()

    mock_getaddrinfo.assert_called_once_with("tor.internal", 9051, proto=socket.IPPROTO_TCP)
    mock_from_port.assert_called_once_with(address="10.11.12.13", port=9051)
    client.close()


@patch("torstatus_updater.tor_client.Controller.from_port")
def test_get_info_lines(mock_from_port) -> None:
    mock_ctrl = MagicMock()
    mock_ctrl.get_info.return_value = "line1\nline2\n250 OK"
    mock_from_port.return_value = mock_ctrl

    client = TorClient("127.0.0.1", 9051)
    client.connect()
    lines = client.get_info_lines("ns/all")
    assert lines == ["line1", "line2", "250 OK"]


@patch("torstatus_updater.tor_client.Controller.from_port")
def test_get_nickname(mock_from_port) -> None:
    mock_ctrl = MagicMock()
    mock_ctrl.get_conf.return_value = "MyRelay"
    mock_from_port.return_value = mock_ctrl

    client = TorClient("127.0.0.1", 9051)
    client.connect()
    assert client.get_nickname() == "MyRelay"
