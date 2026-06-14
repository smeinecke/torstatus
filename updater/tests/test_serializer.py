"""Tests for torstatus_updater.serializer."""

import phpserialize

from torstatus_updater.serializer import dumps, dumps_list


def test_dumps_list() -> None:
    data = ["foo", "bar"]
    result = dumps_list(data)
    assert isinstance(result, bytes)
    loaded = phpserialize.loads(result)
    assert loaded == {0: b"foo", 1: b"bar"}


def test_dumps_empty_list() -> None:
    result = dumps_list([])
    assert phpserialize.loads(result) == {}


def test_dumps_bytes() -> None:
    result = dumps(b"raw bytes")
    assert isinstance(result, bytes)
