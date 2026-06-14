"""Tor control port client built on ``stem``."""

from __future__ import annotations

import logging

from stem.control import Controller

LOG = logging.getLogger(__name__)


class TorClient:
    """Thin wrapper around ``stem.control.Controller``."""

    def __init__(self, host: str, port: int, password: str | None = None) -> None:
        """Store connection parameters (connect lazily via :meth:`connect`)."""
        self.host = host
        self.port = port
        self.password = password
        self._ctrl: Controller | None = None

    def connect(self) -> None:
        """Open the control connection and authenticate."""
        self._ctrl = Controller.from_port(address=self.host, port=self.port)  # type: ignore[arg-type]
        if self.password:
            self._ctrl.authenticate(password=self.password)
        else:
            self._ctrl.authenticate()
        # Wake Tor from dormant mode so descriptors are available
        try:
            self._ctrl.signal("ACTIVE")
        except Exception:
            LOG.warning("SIGNAL ACTIVE failed (may already be active)")

    def close(self) -> None:
        """Close the control connection."""
        if self._ctrl:
            self._ctrl.close()
            self._ctrl = None

    def get_info_lines(self, key: str) -> list[str]:
        """Fetch a multi-line GETINFO value and return it as lines."""
        if not self._ctrl:
            raise RuntimeError("Not connected")
        # stem's get_info returns the full value as a string
        value = self._ctrl.get_info(key)
        if value is None:
            return []
        # Some keys return empty string on missing data; handle gracefully
        return value.splitlines()

    def get_conf(self, key: str) -> str | None:
        """Fetch a single configuration value from Tor."""
        if not self._ctrl:
            raise RuntimeError("Not connected")
        value = self._ctrl.get_conf(key)
        return value

    def get_nickname(self) -> str:
        """Return the local Tor nickname or ``UNKNOWNNICK``."""
        nick = self.get_conf("Nickname")
        return nick if nick else "UNKNOWNNICK"
