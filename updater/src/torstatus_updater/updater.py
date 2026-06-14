"""Orchestrator that fetches Tor data and writes it to the TorStatus DB."""

from __future__ import annotations

import base64
import ipaddress
import logging
import re
from datetime import UTC, datetime

import pymysql.cursors

from . import db as db_mod
from . import dns_lookup, geoip, serializer
from .cache import CacheClient
from .tor_client import TorClient

LOG = logging.getLogger(__name__)

# Regex patterns for descriptor lines
_RE_ROUTER = re.compile(r"^router\s+(\S+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)$")
_RE_OR_ADDRESS = re.compile(r"^or-address\s+(?:\[([^\]]+)\]|([^:]+)):(\d+)$")
_RE_BANDWIDTH = re.compile(r"^bandwidth\s+(\d+)\s+(\d+)\s+(\d+)$")
_RE_PLATFORM = re.compile(r"^platform\s+(.+)$")
_RE_PUBLISHED = re.compile(r"^published\s+(.+)$")
_RE_FINGERPRINT = re.compile(r"^fingerprint\s+(.+)$")
_RE_HIBERNATING = re.compile(r"^hibernating\s+(\d+)$")
_RE_UPTIME = re.compile(r"^uptime\s+(\d+)$")
_RE_CONTACT = re.compile(r"^contact\s+(.+)$")
_RE_EXTRA_INFO_DIGEST = re.compile(r"^extra-info-digest\s+(.+)$")
_RE_FAMILY = re.compile(r"^family\s+(.+)$")
_RE_READ_HISTORY = re.compile(r"^read-history\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\s+\((\d+)\s+s\)\s+(.+)$")
_RE_WRITE_HISTORY = re.compile(r"^write-history\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\s+\((\d+)\s+s\)\s+(.+)$")

# Regex patterns for network-status lines
_RE_NS_R = re.compile(r"^r\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\d+)\s+(\d+)$")
_RE_NS_S = re.compile(r"^s\s+(.+)$")


def _normalize_ip(value: str) -> str:
    """Return a canonical textual IPv4/IPv6 address, or the original value."""
    try:
        return str(ipaddress.ip_address(value.strip("[]")))
    except ValueError:
        return value.strip("[]")


def _parse_timestamp(ts_str: str) -> int:
    """Parse ``YYYY-MM-DD HH:MM:SS`` to a UTC Unix timestamp."""
    dt = datetime.strptime(ts_str, "%Y-%m-%d %H:%M:%S").replace(tzinfo=UTC)
    return int(dt.timestamp())


def _process_history(match: re.Match) -> tuple[str, str, int, bytes]:
    """Return (history_string, ts_str, increment, serialized_data) from a regex match."""
    ts_str = f"{match.group(1)} {match.group(2)}"
    ts = _parse_timestamp(ts_str)
    increment = int(match.group(3))
    nums = list(reversed(match.group(4).split(",")))
    history = []
    offset = 0
    for num in nums:
        history.append(f"{ts - offset}:{num}")
        offset += increment
    history_str = ";".join(history)
    ser = serializer.dumps_list(match.group(4).split(","))
    return history_str, ts_str, increment, ser


def _apply_history(current: dict, match: re.Match, prefix: str) -> None:
    """Store parsed read/write history values in *current*."""
    hist, ts_str, inc, ser = _process_history(match)
    current[prefix.lower()] = hist
    current[f"{prefix}HistoryLAST"] = ts_str
    current[f"{prefix}HistoryINC"] = inc
    current[f"{prefix}HistorySERDATA"] = ser


def _read_until(lines: list[str], start: int, end_marker: str) -> tuple[str, int]:
    """Return joined lines from *start* through *end_marker* and the next index."""
    block: list[str] = []
    i = start
    while i < len(lines):
        line = lines[i].rstrip("\r")
        i += 1
        block.append(line)
        if end_marker in line:
            break
    return "\n".join(block), i


def _decode_tor_base64(value: str) -> bytes:
    """Decode Tor's often-unpadded base64 values."""
    padding = "=" * (-len(value) % 4)
    return base64.b64decode(value + padding)


def _descriptor_params(current: dict) -> tuple:
    """Return DB parameters for a Descriptor row."""
    return (
        current.get("nickname"),
        current.get("address"),
        current.get("ORPort"),
        current.get("DirPort"),
        current.get("Platform"),
        current.get("LastDescriptorPublished"),
        current.get("Fingerprint"),
        current.get("Uptime", 0),
        current.get("BandwidthMAX", 0),
        current.get("BandwidthBURST", 0),
        current.get("BandwidthOBSERVED", 0),
        current.get("OnionKey", ""),
        current.get("SigningKey", ""),
        current.get("Hibernating", 0),
        current.get("Contact", ""),
        current.get("WriteHistoryLAST"),
        current.get("WriteHistoryINC"),
        current.get("WriteHistorySERDATA"),
        current.get("ReadHistoryLAST"),
        current.get("ReadHistoryINC"),
        current.get("ReadHistorySERDATA"),
        current.get("FamilySERDATA"),
        current.get("ExitPolicySERDATA"),
        current.get("DescriptorSignature", ""),
    )


def _network_status_params(current: dict) -> tuple:
    """Return DB parameters for a NetworkStatus row."""
    return (
        current.get("Nickname"),
        current.get("Identity"),
        current.get("Digest"),
        current.get("Publication"),
        current.get("IP"),
        current.get("Hostname", ""),
        current.get("ORPort"),
        current.get("DirPort"),
        1 if current.get("Authority") else 0,
        1 if current.get("BadDirectory") else 0,
        1 if current.get("BadExit") else 0,
        1 if current.get("Exit") else 0,
        1 if current.get("Fast") else 0,
        1 if current.get("Guard") else 0,
        1 if current.get("Named") else 0,
        1 if current.get("Stable") else 0,
        1 if current.get("Running") else 0,
        1 if current.get("Valid") else 0,
        1 if current.get("V2Dir") else 0,
        1 if current.get("HSDir") else 0,
        current.get("Country", ""),
    )


def _insert_network_status(
    cursor: pymysql.cursors.Cursor,
    insert_ns: str,
    current: dict,
    processed: int,
    router_count: int,
) -> int:
    """Insert a pending NetworkStatus row, returning the updated count."""
    if not current.get("Nickname"):
        return processed
    processed += 1
    LOG.debug("Processing router %s (%d/%d)...", current["Nickname"], processed, router_count)
    cursor.execute(insert_ns, _network_status_params(current))
    return processed


def update_descriptors(
    tor: TorClient,
    database: db_mod.Database,
    descriptor_table: int,
) -> int:
    """Fetch ``desc/all-recent`` and populate Descriptor / Bandwidth / ORAddresses tables."""
    LOG.info("Fetching descriptors...")
    lines = tor.get_info_lines("desc/all-recent")

    insert_descriptor = (
        f"INSERT INTO Descriptor{descriptor_table} "  # nosec B608
        "(Name, IP, ORPort, DirPort, Platform, LastDescriptorPublished, Fingerprint, "
        "Uptime, BandwidthMAX, BandwidthBURST, BandwidthOBSERVED, OnionKey, SigningKey, "
        "Hibernating, Contact, WriteHistoryLAST, WriteHistoryINC, WriteHistorySERDATA, "
        "ReadHistoryLAST, ReadHistoryINC, ReadHistorySERDATA, FamilySERDATA, "
        "ExitPolicySERDATA, DescriptorSignature) VALUES "
        "(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"
    )
    insert_bandwidth = f"INSERT INTO Bandwidth{descriptor_table} (fingerprint, `read`, `write`) VALUES (%s, %s, %s)"  # nosec B608
    insert_or = f"INSERT INTO ORAddresses{descriptor_table} (descriptor_id, address, port) VALUES (%s, %s, %s)"  # nosec B608

    cursor = database.cursor()
    return _update_descriptors_indexed(tor, lines, cursor, insert_descriptor, insert_bandwidth, insert_or)


def _update_descriptors_indexed(
    tor: TorClient,
    lines: list[str],
    cursor: pymysql.cursors.Cursor,
    insert_descriptor: str,
    insert_bandwidth: str,
    insert_or: str,
) -> int:
    router_count = 0
    current: dict = {}
    or_addresses: list[dict] = []
    i = 0
    total = len(lines)

    while i < total:
        line = lines[i].rstrip("\r")
        i += 1
        if line == "250 OK":
            break

        # router
        m = _RE_ROUTER.match(line)
        if m:
            router_count += 1
            current = {
                "nickname": m.group(1),
                "address": _normalize_ip(m.group(2)),
                "ORPort": int(m.group(3)),
                "DirPort": int(m.group(5)),
                "Hibernating": 0,
            }
            or_addresses = []
            continue

        # or-address
        m = _RE_OR_ADDRESS.match(line)
        if m:
            addr = m.group(1) or m.group(2) or ""
            or_addresses.append({"address": _normalize_ip(addr), "port": int(m.group(3))})
            continue

        # bandwidth
        m = _RE_BANDWIDTH.match(line)
        if m:
            current["BandwidthMAX"] = int(m.group(1))
            current["BandwidthBURST"] = int(m.group(2))
            current["BandwidthOBSERVED"] = int(m.group(3))
            continue

        # platform
        m = _RE_PLATFORM.match(line)
        if m:
            current["Platform"] = m.group(1)
            continue

        # published
        m = _RE_PUBLISHED.match(line)
        if m:
            current["LastDescriptorPublished"] = m.group(1)
            continue

        # fingerprint
        m = _RE_FINGERPRINT.match(line)
        if m:
            current["Fingerprint"] = m.group(1).replace(" ", "")
            continue

        # hibernating
        m = _RE_HIBERNATING.match(line)
        if m:
            current["Hibernating"] = int(m.group(1))
            continue

        # uptime
        m = _RE_UPTIME.match(line)
        if m:
            current["Uptime"] = int(m.group(1))
            continue

        # onion-key (multi-line)
        if line == "onion-key":
            current["OnionKey"], i = _read_until(lines, i, "-----END RSA PUBLIC KEY-----")
            continue

        # signing-key (multi-line)
        if line == "signing-key":
            current["SigningKey"], i = _read_until(lines, i, "-----END RSA PUBLIC KEY-----")
            continue

        # contact
        m = _RE_CONTACT.match(line)
        if m:
            current["Contact"] = m.group(1)
            continue

        # extra-info-digest
        m = _RE_EXTRA_INFO_DIGEST.match(line)
        if m:
            current["Digest"] = m.group(1)
            continue

        # family
        m = _RE_FAMILY.match(line)
        if m:
            current["FamilySERDATA"] = serializer.dumps_list(m.group(1).split())
            continue

        # exit policy
        if line.startswith("accept ") or line.startswith("reject "):
            policy = re.sub(r"[^\w\d :.*/\-]", "", line)
            current["exitpolicy"] = current.get("exitpolicy", "") + policy + "!"
            continue

        # read-history
        m = _RE_READ_HISTORY.match(line)
        if m:
            _apply_history(current, m, "Read")
            continue

        # write-history
        m = _RE_WRITE_HISTORY.match(line)
        if m:
            _apply_history(current, m, "Write")
            continue

        # router-signature (end of descriptor)
        if line == "router-signature":
            current["DescriptorSignature"], i = _read_until(lines, i, "-----END SIGNATURE-----")

            # serialize exit policy
            ep = current.get("exitpolicy", "").rstrip("!")
            current["ExitPolicySERDATA"] = serializer.dumps_list(ep.split("!")) if ep else serializer.dumps_list([])

            if not current.get("FamilySERDATA"):
                current["FamilySERDATA"] = b""

            # Fetch extra-info bandwidth history if a digest is present
            if current.get("Digest"):
                _fetch_extra_info(tor, current)

            # Insert
            cursor.execute(insert_descriptor, _descriptor_params(current))
            router_id = cursor.lastrowid

            cursor.execute(
                insert_bandwidth,
                (
                    current.get("Fingerprint"),
                    current.get("read", ""),
                    current.get("write", ""),
                ),
            )

            for item in or_addresses:
                cursor.execute(insert_or, (router_id, item["address"], item["port"]))

            current = {}
            or_addresses = []
            continue

    return router_count


def _fetch_extra_info(tor: TorClient, current: dict) -> None:
    """Fetch extra-info data for *current* router and overwrite bandwidth history."""
    digest = current["Digest"].split()[0]
    try:
        lines = tor.get_info_lines(f"extra-info/digest/{digest}")
    except Exception as exc:
        LOG.warning("Failed to fetch extra-info for %s: %s", digest, exc)
        return

    for line in lines:
        line = line.rstrip("\r")
        if line.startswith("250 OK") or line.startswith("552"):
            break

        m = _RE_READ_HISTORY.match(line)
        if m:
            _apply_history(current, m, "Read")
            continue

        m = _RE_WRITE_HISTORY.match(line)
        if m:
            _apply_history(current, m, "Write")


def update_network_status(
    tor: TorClient,
    database: db_mod.Database,
    descriptor_table: int,
    ip_list: list,
    router_count: int,
) -> None:
    """Fetch ``ns/all`` and populate the NetworkStatus table."""
    LOG.info("Fetching network status...")
    lines = tor.get_info_lines("ns/all")

    cursor = database.cursor()
    insert_ns = (
        f"INSERT INTO NetworkStatus{descriptor_table} "  # nosec B608
        "(Name, Fingerprint, DescriptorHash, LastDescriptorPublished, IP, Hostname, "
        "ORPort, DirPort, FAuthority, FBadDirectory, FBadExit, FExit, FFast, FGuard, "
        "FNamed, FStable, FRunning, FValid, FV2Dir, FHSDir, CountryCode) VALUES "
        "(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"
    )

    current: dict = {}
    processed = 0
    i = 0
    total = len(lines)

    while i < total:
        line = lines[i].rstrip("\r")
        i += 1
        if line == "250 OK":
            break

        # r line
        m = _RE_NS_R.match(line)
        if m or line == ".":
            if current:
                processed = _insert_network_status(cursor, insert_ns, current, processed, router_count)
                current = {}

            if m:
                current["Nickname"] = m.group(1)
                current["Identity"] = _decode_tor_base64(m.group(2)).hex()
                current["Digest"] = m.group(3)
                current["Publication"] = f"{m.group(4)} {m.group(5)}"
                current["IP"] = _normalize_ip(m.group(6))
                current["ORPort"] = int(m.group(7))
                current["DirPort"] = int(m.group(8))
                current["Country"] = geoip.get_country(current["IP"], ip_list)
            continue

        # s line (flags)
        m = _RE_NS_S.match(line)
        if m:
            for flag in m.group(1).split():
                current[flag] = 1
            continue

    # Flush the final router
    _insert_network_status(cursor, insert_ns, current, processed, router_count)


def update_hostnames(
    database: db_mod.Database,
    descriptor_table: int,
    cache_client: CacheClient | None,
    router_count: int,
) -> None:
    """Look up reverse hostnames for every router in NetworkStatus."""
    LOG.info("Updating hostnames...")
    cursor = database.cursor()
    cursor.execute(f"SELECT Fingerprint, IP FROM NetworkStatus{descriptor_table}")  # nosec B608
    update_sql = f"UPDATE NetworkStatus{descriptor_table} SET Hostname = %s WHERE Fingerprint = %s"  # nosec B608

    lookup_counter = 0
    for row in cursor.fetchall():
        fingerprint, ip = row
        lookup_counter += 1
        LOG.debug("Looking up %s (%d/%d)", ip, lookup_counter, router_count)

        hostname = dns_lookup.lookup(ip, cache_client=cache_client)
        with database.cursor() as uc:
            uc.execute(update_sql, (hostname, fingerprint))

    LOG.info("Updated %d hostnames", lookup_counter)
