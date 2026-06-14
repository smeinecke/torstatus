# TorStatus Updater

Python updater that fetches Tor network data via the control port and populates the TorStatus MariaDB database.

## Architecture

The package is split into focused modules under `src/torstatus_updater/`:

- **`config.py`** — Parses the shared PHP `config.php` (regex-based, includes env-var fallback).
- **`tor_client.py`** — Thin wrapper around `stem.control.Controller` for Tor control-port communication.
- **`geoip.py`** — Binary-search country lookup over Tor's IPv4 and IPv6 GeoIP CSV files.
- **`dns_lookup.py`** — IPv4/IPv6 reverse DNS with an in-process LRU cache and optional shared cache integration.
- **`cache.py`** — Memcached and Redis/Valkey cache adapters with a no-cache fallback.
- **`serializer.py`** — Wrapper around `phpserialize` for PHP frontend compatibility.
- **`db.py`** — MariaDB helpers with atomic table-flipping (Descriptor1/2, NetworkStatus1/2, etc.).
- **`updater.py`** — Orchestrates descriptor parsing, network-status parsing, hostname lookups, and DB writes.
- **`__main__.py`** — CLI entry point (`python -m torstatus_updater`).

## Running

### Local / manual

```bash
uv run python -m torstatus_updater
```

With debug logging:

```bash
uv run python -m torstatus_updater --debug
```

### Docker

The updater is built as part of the Docker Compose stack:

```bash
docker compose build updater
docker compose up updater
```

### systemd timer (production)

Copy the unit files and enable the timer:

```bash
sudo cp torstatus-updater.service torstatus-updater.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now torstatus-updater.timer
```

The timer triggers every 15 minutes. The service runs once per trigger (`Type=oneshot`) and retries on failure after 15 seconds.

Check status:

```bash
sudo systemctl list-timers torstatus-updater.timer
sudo systemctl status torstatus-updater.service
```

## Development

### Setup

```bash
cd updater
uv sync --extra dev
```

### Tests

```bash
uv run make test
# or directly
uv run python -m pytest tests -v
```

### Lint / format / type-check

```bash
uv run make          # runs full validate + test pipeline
uv run make fix      # auto-fix ruff issues and reformat
uv run make check    # ruff check only
uv run make test     # pytest only
```

Tools in the pipeline:
- **ruff** — formatting and linting
- **radon** — cyclomatic complexity
- **bandit** — security linting
- **pyright** — static type checking
- **vulture** — dead-code detection

## Configuration

The updater reads the same `config.php` used by the PHP frontend (looked up as `./config.php` or `../nginx/web/config.php`).

Required settings:
- `SQL_Server`, `SQL_User`, `SQL_Pass`, `SQL_Catalog` — MariaDB connection
- `LocalTorServerIP`, `LocalTorServerControlPort`, `LocalTorServerPassword` — Tor control port

Cache settings:
- `cache_backend` — `memcached`, `redis`, `valkey`, or `none`
- `cache_host` — optional explicit host; defaults to `memcached` for Memcached and `valkey` for Redis/Valkey in Docker
- `cache_port` — optional explicit port; defaults to `11211` for Memcached and `6379` for Redis/Valkey
- `memcached_host` — deprecated compatibility alias for older configs

## Migration notes

This Python package replaces the legacy `tns_update.pl` Perl script and the intermediate `tns_update.py` monolith. It keeps parity with the original logic while adding IPv6 OR-address parsing, IPv6 GeoIP support, Redis/Valkey caching, PHP serialization, and atomic table-flipping. It improves:
- Connection reuse via `stem.Controller`
- Structured error handling and logging
- Modular testability with pytest
