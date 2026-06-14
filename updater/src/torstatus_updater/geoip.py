"""GeoIP country lookup using a binary search over Tor's GeoIP CSV."""

import csv
import ipaddress


def init_countries(geoip_path: str = "/usr/share/tor/geoip") -> list[list]:
    """Load the Tor GeoIP CSV into a sorted list of [from, to, country]."""
    ip_list: list[list] = []
    with open(geoip_path, encoding="utf-8") as fh:
        for row in csv.reader(fh):
            if not row or row[0].startswith("#"):
                continue
            ip_list.append([int(row[0]), int(row[1]), row[2]])
    return ip_list


def get_country(ip_str: str, ip_list: list[list]) -> str:
    """Binary search the GeoIP list for a country code.

    Returns an empty string when the IP is not found or the country is ``??``.
    """
    if not ip_list:
        return ""
    try:
        int_ip = int(ipaddress.IPv4Address(ip_str))
    except ValueError:
        return ""
    left, right = 0, len(ip_list) - 1
    while True:
        index = (left + right) // 2
        ip_from, ip_to, country = ip_list[index]
        if ip_from <= int_ip <= ip_to:
            return "" if country == "??" else country
        if left == right:
            return ""
        if ip_from > int_ip:
            right = index
        else:
            left = index + 1
