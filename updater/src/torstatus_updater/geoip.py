"""GeoIP country lookup using Tor's IPv4 and IPv6 GeoIP CSV files."""

from __future__ import annotations

import csv
import ipaddress
from pathlib import Path

type CountryRange = tuple[int, int, str]


def init_countries(
    geoip_path: str = "/usr/share/tor/geoip",
    geoip6_path: str = "/usr/share/tor/geoip6",
) -> list[CountryRange]:
    """Load Tor GeoIP CSV files into sorted integer IP ranges."""
    ranges = _read_ranges(geoip_path)
    if Path(geoip6_path).exists():
        ranges.extend(_read_ranges(geoip6_path))
    ranges.sort(key=lambda row: row[0])
    return ranges


def _read_ranges(path: str) -> list[CountryRange]:
    ranges: list[CountryRange] = []
    with open(path, encoding="utf-8") as fh:
        for row in csv.reader(fh):
            if len(row) < 3 or row[0].startswith("#"):
                continue
            try:
                ip_from = _range_endpoint(row[0])
                ip_to = _range_endpoint(row[1])
            except ValueError:
                continue
            ranges.append((ip_from, ip_to, row[2]))
    return ranges


def _range_endpoint(value: str) -> int:
    value = value.strip().strip('"')
    if value.isdigit():
        return int(value)
    return int(ipaddress.ip_address(value))


def get_country(ip_str: str, ip_list: list[CountryRange]) -> str:
    """Binary search the GeoIP list for an IPv4 or IPv6 country code."""
    if not ip_list:
        return ""
    try:
        int_ip = int(ipaddress.ip_address(ip_str.strip("[]")))
    except ValueError:
        return ""

    left, right = 0, len(ip_list) - 1
    while left <= right:
        index = (left + right) // 2
        ip_from, ip_to, country = ip_list[index]
        if ip_from <= int_ip <= ip_to:
            return "" if country == "??" else country
        if int_ip < ip_from:
            right = index - 1
        else:
            left = index + 1

    return ""
