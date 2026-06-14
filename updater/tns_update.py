#!/usr/bin/env python3
"""
tns_update.py
Copyright (c) 2007-2008 Kasimir Gabert / 2025 TorStatus contributors
A Python script designed to update the database of TorStatus for the
most current information from a local Tor server.

    This program is part of TorStatus

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published
    by the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
"""

import argparse
import base64
import csv
import ipaddress
import logging
import os
import re
import socket
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

import pymysql
import pymemcache.client.base
import phpserialize

LOG = logging.getLogger("tns_update")

TIMEOUT = 1
CACHE = {}


def parse_config(path: str) -> dict:
    """Parse a simple PHP config file into a Python dict."""
    config = {}
    with open(path, "r", encoding="utf-8", errors="ignore") as fh:
        for line in fh:
            m = re.match(r'^\$(\w+)\s*=\s*(.*?);', line.strip())
            if not m:
                continue
            key = m.group(1)
            value = m.group(2).strip().strip('"').strip("'")
            # Resolve environment variables used in docker config
            if value.startswith('isset($_ENV['):
                env_match = re.search(r"$_ENV\['([^']+)'\]\)\s*\?\s*\$_ENV\['[^']+'\]\s*:\s*'([^']*)'", line)
                if env_match:
                    value = os.environ.get(env_match.group(1), env_match.group(2))
                else:
                    value = ''
            config[key] = value
    return config


def init_countries(geoip_path: str = "/usr/share/tor/geoip") -> list:
    """Load the Tor GeoIP CSV into a sorted list of [from, to, country]."""
    ip_list = []
    with open(geoip_path, "r", encoding="utf-8") as fh:
        for row in csv.reader(fh):
            if not row or row[0].startswith("#"):
                continue
            ip_list.append([int(row[0]), int(row[1]), row[2]])
    return ip_list


def get_country(ip_str: str, ip_list: list) -> str:
    """Binary search the GeoIP list for a country code."""
    try:
        int_ip = int(ipaddress.IPv4Address(ip_str))
    except ValueError:
        return ''
    left, right = 0, len(ip_list) - 1
    while True:
        index = (left + right) // 2
        entry = ip_list[index]
        ip_from, ip_to, country = entry
        if ip_from <= int_ip <= ip_to:
            return '' if country == '??' else country
        if left == right:
            return ''
        if ip_from > int_ip:
            right = index
        else:
            left = index + 1


def lookup(ip: str) -> str:
    """Reverse DNS lookup with timeout and caching."""
    if not re.match(r'\d+\.\d+\.\d+\.\d+', ip):
        return ip
    if ip in CACHE:
        return CACHE[ip] or ip
    try:
        result = socket.gethostbyaddr(ip)
        hostname = result[0]
    except (socket.herror, socket.timeout, OSError):
        hostname = None
    CACHE[ip] = hostname
    return hostname or ip


class TorController:
    """Simple synchronous Tor control port client."""

    def __init__(self, host: str, port: int, password: str | None = None):
        self.host = host
        self.port = port
        self.password = password
        self.sock: socket.socket | None = None
        self._logfile: logging.Logger = LOG.getChild("tor")

    def connect(self) -> None:
        self.sock = socket.create_connection((self.host, self.port), timeout=30)
        self.sock.settimeout(30)

    def close(self) -> None:
        if self.sock:
            self.sock.close()
            self.sock = None

    def send(self, cmd: str) -> None:
        if not self.sock:
            raise RuntimeError("Not connected")
        self.sock.sendall(f"{cmd}\r\n".encode())

    def readline(self) -> str:
        if not self.sock:
            raise RuntimeError("Not connected")
        buf = b""
        while True:
            ch = self.sock.recv(1)
            if not ch:
                raise ConnectionResetError("Tor control connection closed")
            if ch == b"\n":
                break
            buf += ch
        line = buf.decode().rstrip("\r")
        self._logfile.debug("tor <- %s", line)
        return line

    def authenticate(self) -> None:
        pwd = f' "{self.password}"' if self.password and self.password != "null" else ""
        self.send(f"AUTHENTICATE{pwd}")
        resp = self.readline()
        if not resp.startswith("250"):
            raise RuntimeError(f"Tor authentication failed: {resp}")
        self.send("SIGNAL ACTIVE")
        self.readline()  # consume response

    def get_info(self, key: str) -> list[str]:
        """Send a GETINFO command and collect all reply lines until 250 OK."""
        self.send(f"GETINFO {key}")
        lines = []
        while True:
            line = self.readline()
            lines.append(line)
            if line.startswith("250 OK"):
                break
            if line.startswith("552"):
                raise RuntimeError(f"GETINFO {key} failed: {line}")
        return lines

    def get_conf(self, key: str) -> str | None:
        self.send(f"GETCONF {key}")
        line = self.readline()
        m = re.match(rf"250-{key}=(.*)$", line)
        if m:
            return m.group(1)
        return None


def parse_descriptors(tor: TorController, dbh, descriptor_table: int, config: dict, debug: bool) -> int:
    """Fetch desc/all-recent, parse each router, and insert into DB."""
    cursor = dbh.cursor()

    # Truncate staging tables
    for tbl in (f"Bandwidth{descriptor_table}", f"Descriptor{descriptor_table}", f"ORAddresses{descriptor_table}"):
        cursor.execute(f"TRUNCATE TABLE {tbl}")

    # Prepare insert statements
    insert_descriptor = (
        f"INSERT INTO Descriptor{descriptor_table} "
        "(Name, IP, ORPort, DirPort, Platform, LastDescriptorPublished, Fingerprint, "
        "Uptime, BandwidthMAX, BandwidthBURST, BandwidthOBSERVED, OnionKey, SigningKey, "
        "Hibernating, Contact, WriteHistoryLAST, WriteHistoryINC, WriteHistorySERDATA, "
        "ReadHistoryLAST, ReadHistoryINC, ReadHistorySERDATA, FamilySERDATA, "
        "ExitPolicySERDATA, DescriptorSignature) VALUES "
        "(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"
    )

    insert_bandwidth = f"INSERT INTO Bandwidth{descriptor_table} (fingerprint, `read`, `write`) VALUES (%s, %s, %s)"
    insert_or = f"INSERT INTO ORAddresses{descriptor_table} (descriptor_id, address, port) VALUES (%s, %s, %s)"

    tor.send("GETINFO desc/all-recent")
    response = tor.readline()
    if not response.startswith("250+"):
        raise RuntimeError(f"Failed to retrieve descriptors: {response}")

    router_count = 0
    current = {}
    or_addresses = []

    while True:
        line = tor.readline()
        if line.startswith("250 OK"):
            break

        # router line
        m = re.match(r"^router\s+(\S+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)$", line)
        if m:
            router_count += 1
            current = {
                "nickname": m.group(1),
                "address": m.group(2),
                "ORPort": int(m.group(3)),
                "DirPort": int(m.group(5)),
                "Hibernating": 0,
            }
            or_addresses = []
            continue

        # or-address line
        m = re.match(r"^or-address\s+(.+):(\d+)$", line)
        if m:
            addr = m.group(1)
            if addr.startswith("[") and addr.endswith("]"):
                addr = addr[1:-1]
            or_addresses.append({"address": addr, "port": int(m.group(2))})
            continue

        # bandwidth line
        m = re.match(r"^bandwidth\s+(\d+)\s+(\d+)\s+(\d+)$", line)
        if m:
            current["BandwidthMAX"] = int(m.group(1))
            current["BandwidthBURST"] = int(m.group(2))
            current["BandwidthOBSERVED"] = int(m.group(3))
            continue

        # platform line
        m = re.match(r"^platform\s+(.+)$", line)
        if m:
            current["Platform"] = m.group(1)
            continue

        # published line
        m = re.match(r"^published\s+(.+)$", line)
        if m:
            current["LastDescriptorPublished"] = m.group(1)
            continue

        # fingerprint line
        m = re.match(r"^fingerprint\s+(.+)$", line)
        if m:
            current["Fingerprint"] = m.group(1).replace(" ", "")
            continue

        # hibernating line
        m = re.match(r"^hibernating\s+(\d+)$", line)
        if m:
            current["Hibernating"] = int(m.group(1))
            continue

        # uptime line
        m = re.match(r"^uptime\s+(\d+)$", line)
        if m:
            current["Uptime"] = int(m.group(1))
            continue

        # onion-key (multi-line)
        if line == "onion-key":
            key_lines = []
            while True:
                kline = tor.readline()
                if "-----END RSA PUBLIC KEY-----" in kline:
                    break
                key_lines.append(kline)
            current["OnionKey"] = "\n".join(key_lines)
            continue

        # signing-key (multi-line)
        if line == "signing-key":
            key_lines = []
            while True:
                kline = tor.readline()
                if "-----END RSA PUBLIC KEY-----" in kline:
                    break
                key_lines.append(kline)
            current["SigningKey"] = "\n".join(key_lines)
            continue

        # contact line
        m = re.match(r"^contact\s+(.+)$", line)
        if m:
            current["Contact"] = m.group(1)
            continue

        # extra-info-digest line
        m = re.match(r"^extra-info-digest\s+(.+)$", line)
        if m:
            current["Digest"] = m.group(1)
            continue

        # family line
        m = re.match(r"^family\s+(.+)$", line)
        if m:
            current["FamilySERDATA"] = phpserialize.dumps(m.group(1).split())
            continue

        # exit policy line
        if line.startswith("accept ") or line.startswith("reject "):
            policy = re.sub(r"[^\w\d :.*/\-]", "", line)
            current["exitpolicy"] = current.get("exitpolicy", "") + policy + "!"
            continue

        # read-history line
        m = re.match(r"^read-history\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\s+\((\d+)\s+s\)\s+(.+)$", line)
        if m:
            ts_str = f"{m.group(1)} {m.group(2)}"
            ts = int(datetime.strptime(ts_str, "%Y-%m-%d %H:%M:%S").replace(tzinfo=timezone.utc).timestamp())
            increment = int(m.group(3))
            nums = list(reversed(m.group(4).split(",")))
            readhistory = []
            offset = 0
            for num in nums:
                readhistory.append(f"{ts - offset}:{num}")
                offset += increment
            current["read"] = ";".join(readhistory)
            current["ReadHistoryLAST"] = ts_str
            current["ReadHistoryINC"] = increment
            current["ReadHistorySERDATA"] = phpserialize.dumps(m.group(4).split(","))
            continue

        # write-history line
        m = re.match(r"^write-history\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\s+\((\d+)\s+s\)\s+(.+)$", line)
        if m:
            ts_str = f"{m.group(1)} {m.group(2)}"
            ts = int(datetime.strptime(ts_str, "%Y-%m-%d %H:%M:%S").replace(tzinfo=timezone.utc).timestamp())
            increment = int(m.group(3))
            nums = list(reversed(m.group(4).split(",")))
            writehistory = []
            offset = 0
            for num in nums:
                writehistory.append(f"{ts - offset}:{num}")
                offset += increment
            current["write"] = ";".join(writehistory)
            current["WriteHistoryLAST"] = ts_str
            current["WriteHistoryINC"] = increment
            current["WriteHistorySERDATA"] = phpserialize.dumps(m.group(4).split(","))
            continue

        # router-signature (end of descriptor)
        if line == "router-signature":
            sig_lines = []
            while True:
                sline = tor.readline()
                if "-----END SIGNATURE-----" in sline:
                    break
                sig_lines.append(sline)
            current["DescriptorSignature"] = "\n".join(sig_lines)

            # serialize exit policy
            ep = current.get("exitpolicy", "").rstrip("!")
            current["ExitPolicySERDATA"] = phpserialize.dumps(ep.split("!")) if ep else phpserialize.dumps([])

            if not current.get("FamilySERDATA"):
                current["FamilySERDATA"] = b""

            # Fetch extra-info if digest present
            if current.get("Digest"):
                _fetch_extra_info(tor, current, config, debug)

            # Insert into DB
            cursor.execute(insert_descriptor, (
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
            ))
            router_id = cursor.lastrowid

            cursor.execute(insert_bandwidth, (
                current.get("Fingerprint"),
                current.get("read", ""),
                current.get("write", ""),
            ))

            for item in or_addresses:
                cursor.execute(insert_or, (router_id, item["address"], item["port"]))

            current = {}
            or_addresses = []
            continue

    dbh.commit()
    LOG.info("Number of routers: %d", router_count)
    return router_count


def _fetch_extra_info(tor: TorController, current: dict, config: dict, debug: bool) -> None:
    """Open a secondary control connection to fetch extra-info data."""
    digest = current["Digest"].split()[0]
    extra = TorController(
        config["LocalTorServerIP"],
        int(config["LocalTorServerControlPort"]),
        config["LocalTorServerPassword"] if config.get("LocalTorServerPassword") != "null" else None,
    )
    extra.connect()
    extra.authenticate()
    extra.send(f"GETINFO extra-info/digest/{digest}")

    while True:
        line = extra.readline()
        if line.startswith("250 OK") or line.startswith("552"):
            break

        # read-history from extra-info
        m = re.match(r"^read-history\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\s+\((\d+)\s+s\)\s+(.+)$", line)
        if m:
            ts_str = f"{m.group(1)} {m.group(2)}"
            ts = int(datetime.strptime(ts_str, "%Y-%m-%d %H:%M:%S").replace(tzinfo=timezone.utc).timestamp())
            increment = int(m.group(3))
            nums = list(reversed(m.group(4).split(",")))
            readhistory = []
            offset = 0
            for num in nums:
                readhistory.append(f"{ts - offset}:{num}")
                offset += increment
            current["read"] = ";".join(readhistory)
            current["ReadHistoryLAST"] = ts_str
            current["ReadHistoryINC"] = increment
            current["ReadHistorySERDATA"] = phpserialize.dumps(m.group(4).split(","))
            continue

        # write-history from extra-info
        m = re.match(r"^write-history\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\s+\((\d+)\s+s\)\s+(.+)$", line)
        if m:
            ts_str = f"{m.group(1)} {m.group(2)}"
            ts = int(datetime.strptime(ts_str, "%Y-%m-%d %H:%M:%S").replace(tzinfo=timezone.utc).timestamp())
            increment = int(m.group(3))
            nums = list(reversed(m.group(4).split(",")))
            writehistory = []
            offset = 0
            for num in nums:
                writehistory.append(f"{ts - offset}:{num}")
                offset += increment
            current["write"] = ";".join(writehistory)
            current["WriteHistoryLAST"] = ts_str
            current["WriteHistoryINC"] = increment
            current["WriteHistorySERDATA"] = phpserialize.dumps(m.group(4).split(","))

    extra.close()


def parse_network_status(tor: TorController, dbh, descriptor_table: int, ip_list: list, router_count: int, debug: bool) -> None:
    """Fetch ns/all and populate NetworkStatus table."""
    cursor = dbh.cursor()
    cursor.execute(f"TRUNCATE TABLE NetworkStatus{descriptor_table}")

    insert_ns = (
        f"INSERT INTO NetworkStatus{descriptor_table} "
        "(Name, Fingerprint, DescriptorHash, LastDescriptorPublished, IP, Hostname, "
        "ORPort, DirPort, FAuthority, FBadDirectory, FBadExit, FExit, FFast, FGuard, "
        "FNamed, FStable, FRunning, FValid, FV2Dir, FHSDir, CountryCode) VALUES "
        "(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"
    )

    tor.send("GETINFO ns/all")
    response = tor.readline()
    if not response.startswith("250+"):
        raise RuntimeError(f"Failed to retrieve network status: {response}")

    current = {}
    processed = 0

    while True:
        line = tor.readline()
        if line.startswith("250 OK"):
            break

        # r line
        m = re.match(r"^r\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\d+)\s+(\d+)$", line)
        if m or line == ".":
            if current.get("Nickname"):
                processed += 1
                LOG.debug("Processing router %s (%d/%d)...", current["Nickname"], processed, router_count)
                cursor.execute(insert_ns, (
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
                ))
                current = {}

            if m:
                current["Nickname"] = m.group(1)
                current["Identity"] = base64.b64decode(m.group(2)).hex()
                current["Digest"] = m.group(3)
                current["Publication"] = f"{m.group(4)} {m.group(5)}"
                current["IP"] = m.group(6)
                current["ORPort"] = int(m.group(7))
                current["DirPort"] = int(m.group(8))
                current["Country"] = get_country(m.group(6), ip_list)
            continue

        # s line (flags)
        m = re.match(r"^s\s+(.+)$", line)
        if m:
            for flag in m.group(1).split():
                current[flag] = 1
            continue

    dbh.commit()


def update_hostnames(dbh, descriptor_table: int, memcached_client, router_count: int, debug: bool) -> None:
    """Look up and cache hostnames for all routers."""
    cursor = dbh.cursor()
    cursor.execute(f"SELECT Fingerprint, IP FROM NetworkStatus{descriptor_table}")

    update_sql = f"UPDATE NetworkStatus{descriptor_table} SET Hostname = %s WHERE Fingerprint = %s"
    lookup_counter = 0
    for row in cursor.fetchall():
        fingerprint, ip = row
        lookup_counter += 1
        if debug:
            LOG.debug("Looking up %s (%d/%d)", ip, lookup_counter, router_count)

        cache_key = f"torstatus_host_{ip}"
        hostname = memcached_client.get(cache_key)
        if not hostname:
            if debug:
                LOG.debug("No cached entry found, executing lookup")
            hostname = lookup(ip)

        if not hostname:
            hostname = ip

        if debug:
            LOG.debug("Hostname: %s, fingerprint: %s, ip: %s", hostname, fingerprint, ip)

        with dbh.cursor() as uc:
            uc.execute(update_sql, (hostname, fingerprint))

        memcached_client.set(cache_key, hostname, expire=86400)

    dbh.commit()


def main() -> int:
    parser = argparse.ArgumentParser(description="Update TorStatus database")
    parser.add_argument("--debug", action="store_true", help="Enable debug output")
    args = parser.parse_args()

    logging.basicConfig(
        level=logging.DEBUG if args.debug else logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )

    start_time = time.time()

    # Load configuration
    config_file = "./config.php"
    if not os.path.exists(config_file):
        config_file = "../nginx/web/config.php"
    config = parse_config(config_file)

    # Memcached
    memcached = pymemcache.client.base.Client(
        (config.get("memcached_host", "memcached"), 11211),
    )

    # GeoIP
    ip_list = init_countries()

    # Database
    dbh = pymysql.connect(
        host=config.get("SQL_Server", "mariadb"),
        user=config.get("SQL_User", "torstatus"),
        password=config.get("SQL_Pass", "torstatus"),
        database=config.get("SQL_Catalog", "torstatus"),
        autocommit=False,
        cursorclass=pymysql.cursors.Cursor,
    )

    # Verify database is installed
    with dbh.cursor() as cur:
        cur.execute("SELECT count(*) FROM Status")
        if cur.fetchone()[0] < 1:
            LOG.error("Database not installed")
            return 1

    # Determine which tables to update
    with dbh.cursor() as cur:
        cur.execute("SELECT ActiveNetworkStatusTable, ActiveDescriptorTable FROM Status WHERE ID = 1")
        row = cur.fetchone()
        descriptor_table = 2 if row and "1" in row[0] else 1

    # Tor control connection
    tor_password = config.get("LocalTorServerPassword") if config.get("LocalTorServerPassword") != "null" else None
    tor = TorController(
        config.get("LocalTorServerIP", "tor"),
        int(config.get("LocalTorServerControlPort", "9051")),
        tor_password,
    )
    tor.connect()
    tor.authenticate()

    try:
        # Phase 1: descriptors
        router_count = parse_descriptors(tor, dbh, descriptor_table, config, args.debug)

        # Phase 2: network status
        parse_network_status(tor, dbh, descriptor_table, ip_list, router_count, args.debug)

        # Phase 3: hostname lookups
        update_hostnames(dbh, descriptor_table, memcached, router_count, args.debug)

        # Fix future timestamps
        with dbh.cursor() as cur:
            cur.execute(f"UPDATE Descriptor{descriptor_table} SET LastDescriptorPublished = NOW() WHERE LastDescriptorPublished > NOW()")
            cur.execute(f"UPDATE NetworkStatus{descriptor_table} SET LastDescriptorPublished = NOW() WHERE LastDescriptorPublished > NOW()")

        # Update opinion source
        tor.send("GETCONF nickname")
        line = tor.readline()
        m = re.match(r"250-Nickname=(.+)$", line)
        nickname = m.group(1) if m else "UNKNOWNNICK"

        with dbh.cursor() as cur:
            cur.execute("TRUNCATE TABLE NetworkStatusSource")
            source_query = "Name = %s"
            source_params = (nickname,)
            if config.get("SourceFingerprint"):
                source_query = "Fingerprint = %s"
                source_params = (config["SourceFingerprint"],)
            cur.execute(
                f"INSERT INTO NetworkStatusSource SELECT * FROM Descriptor{descriptor_table} WHERE {source_query} LIMIT 1",
                source_params,
            )
            cur.execute("UPDATE NetworkStatusSource SET ID=1")

        # Final status update
        end_time = time.time()
        elapsed = int(end_time - start_time)
        with dbh.cursor() as cur:
            cur.execute(
                "UPDATE Status SET LastUpdate = NOW(), LastUpdateElapsed = %s, "
                "ActiveNetworkStatusTable = %s, ActiveDescriptorTable = %s, ActiveORAddressesTable = %s "
                "WHERE ID = 1",
                (elapsed, f"NetworkStatus{descriptor_table}", f"Descriptor{descriptor_table}", f"ORAddresses{descriptor_table}"),
            )

        dbh.commit()
        LOG.info("Update completed in %d seconds", elapsed)

    finally:
        tor.close()
        dbh.close()

    # Touch last_update file
    Path("./last_update").touch()
    return 0


if __name__ == "__main__":
    sys.exit(main())
