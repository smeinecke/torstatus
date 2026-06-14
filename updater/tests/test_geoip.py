"""Tests for torstatus_updater.geoip."""

import csv
import tempfile

from torstatus_updater.geoip import get_country, init_countries


def test_binary_search_hits() -> None:
    """Build a tiny GeoIP CSV and assert binary search returns correct countries."""
    rows = [
        ["16777216", "16777471", "US"],
        ["16777472", "16777727", "GB"],
        ["16777728", "16777983", "DE"],
    ]
    with tempfile.NamedTemporaryFile(mode="w", suffix=".csv", delete=False) as fh:
        writer = csv.writer(fh)
        writer.writerows(rows)
        path = fh.name

    ip_list = init_countries(path)
    assert get_country("1.0.0.0", ip_list) == "US"
    assert get_country("1.0.1.0", ip_list) == "GB"
    assert get_country("1.0.2.0", ip_list) == "DE"
    assert get_country("255.255.255.255", ip_list) == ""


def test_unknown_country() -> None:
    rows = [["16777216", "16777471", "??"]]
    with tempfile.NamedTemporaryFile(mode="w", suffix=".csv", delete=False) as fh:
        writer = csv.writer(fh)
        writer.writerows(rows)
        path = fh.name

    ip_list = init_countries(path)
    assert get_country("1.0.0.0", ip_list) == ""
