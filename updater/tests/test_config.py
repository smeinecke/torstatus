"""Tests for torstatus_updater.config."""

import os
import tempfile

from torstatus_updater.config import parse_config


def test_parse_simple_config() -> None:
    content = """
<?php
$SQL_Server = 'localhost';
$SQL_User = 'torstatus';
$SQL_Pass = 'secret';
$SQL_Catalog = 'torstatus';
$LocalTorServerIP = '127.0.0.1';
$LocalTorServerControlPort = '9051';
$LocalTorServerPassword = 'null';
$redis_uri = '';
$memcached_host = 'memcached';
"""
    with tempfile.NamedTemporaryFile(mode="w", suffix=".php", delete=False) as fh:
        fh.write(content)
        path = fh.name
    try:
        cfg = parse_config(path)
        assert cfg["SQL_Server"] == "localhost"
        assert cfg["SQL_User"] == "torstatus"
        assert cfg["SQL_Pass"] == "secret"
        assert cfg["SQL_Catalog"] == "torstatus"
        assert cfg["LocalTorServerIP"] == "127.0.0.1"
        assert cfg["LocalTorServerControlPort"] == "9051"
        assert cfg["LocalTorServerPassword"] == "null"
        assert cfg["redis_uri"] == ""
        assert cfg["memcached_host"] == "memcached"
    finally:
        os.unlink(path)


def test_parse_env_fallback() -> None:
    content = "<?php\n$REAL_SERVER_IP = isset($_ENV['REAL_SERVER_IP']) ? $_ENV['REAL_SERVER_IP'] : '1.2.3.4';\n"
    with tempfile.NamedTemporaryFile(mode="w", suffix=".php", delete=False) as fh:
        fh.write(content)
        path = fh.name
    os.environ["REAL_SERVER_IP"] = "9.9.9.9"
    try:
        cfg = parse_config(path)
        assert cfg["REAL_SERVER_IP"] == "9.9.9.9"
    finally:
        os.unlink(path)
        del os.environ["REAL_SERVER_IP"]


def test_parse_variable_reference() -> None:
    content = """
<?php
$redis_uri = isset($_ENV['REDIS_URI']) ? $_ENV['REDIS_URI'] : '';
$memcached_host = '127.0.0.1';
"""
    with tempfile.NamedTemporaryFile(mode="w", suffix=".php", delete=False) as fh:
        fh.write(content)
        path = fh.name
    try:
        cfg = parse_config(path)
        assert cfg["redis_uri"] == ""
        assert cfg["memcached_host"] == "127.0.0.1"
    finally:
        os.unlink(path)
