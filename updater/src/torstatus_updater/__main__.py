"""Entry point for the TorStatus updater."""

from __future__ import annotations

import argparse
import logging
import os
import sys
import time
from pathlib import Path

from . import geoip
from .cache import build_cache
from .config import parse_config
from .db import Database
from .tor_client import TorClient
from .updater import update_descriptors, update_hostnames, update_network_status

LOG = logging.getLogger("torstatus_updater")


def main() -> int:
    """Run a full update cycle."""
    parser = argparse.ArgumentParser(description="Update TorStatus database")
    parser.add_argument("--debug", action="store_true", help="Enable debug output")
    args = parser.parse_args()

    logging.basicConfig(
        level=logging.DEBUG if args.debug else logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )
    logging.getLogger("stem").setLevel(logging.INFO)

    start_time = time.time()

    # Load configuration
    config_file = "./config.php"
    if not os.path.exists(config_file):
        config_file = "../nginx/web/config.php"
    config = parse_config(config_file)

    # Shared cache: memcached by default, or Redis/Valkey when configured.
    cache = build_cache(config)

    # GeoIP
    ip_list = geoip.init_countries()

    # Database
    database = Database(
        host=config.get("SQL_Server", "mariadb"),
        user=config.get("SQL_User", "torstatus"),
        password=config.get("SQL_Pass", "torstatus"),
        database=config.get("SQL_Catalog", "torstatus"),
    )

    if not database.check_installed():
        LOG.error("Database not installed")
        return 1

    descriptor_table, _, _, _, _ = database.active_tables()
    LOG.info("Using staging tables suffix %d", descriptor_table)

    database.truncate_staging(descriptor_table)

    # Tor control connection
    tor_password = config.get("LocalTorServerPassword") if config.get("LocalTorServerPassword") != "null" else None
    tor = TorClient(
        host=config.get("LocalTorServerIP", "tor"),
        port=int(config.get("LocalTorServerControlPort", "9051")),
        password=tor_password,
    )
    tor.connect()

    try:
        # Phase 1: descriptors
        router_count = update_descriptors(tor, database, descriptor_table)
        database.commit()
        LOG.info("Inserted %d descriptors", router_count)

        # Phase 2: network status
        update_network_status(tor, database, descriptor_table, ip_list, router_count)
        database.commit()

        # Phase 3: hostname lookups
        update_hostnames(database, descriptor_table, cache, router_count)
        database.commit()

        # Fix future timestamps
        database.fix_future_timestamps(descriptor_table)
        database.commit()

        # Update opinion source
        nickname = tor.get_nickname()
        database.update_network_status_source(
            descriptor_table,
            nickname,
            config.get("SourceFingerprint") or None,
        )
        database.commit()

        # Finalize status row
        elapsed = int(time.time() - start_time)
        database.finalize(descriptor_table, elapsed)
        database.commit()
        LOG.info("Update completed in %d seconds", elapsed)

    finally:
        tor.close()
        database.close()

    # Touch last_update file for the Docker healthcheck
    Path("./last_update").touch()
    return 0


if __name__ == "__main__":
    sys.exit(main())
