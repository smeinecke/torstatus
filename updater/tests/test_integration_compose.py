"""End-to-end test for the TorStatus stack via docker compose.

Spins up mariadb + valkey + fake-tor (control-port stub) + updater +
php-fpm + nginx, waits for the updater to populate the database, then
asserts on the rendered web output and the database contents.

All checks run inside the compose network (`docker compose exec`), so no
host ports are published.
"""

import json
import shutil
import subprocess
import time
import uuid
from collections.abc import Iterator
from pathlib import Path

import pytest

pytestmark = pytest.mark.integration

COMPOSE_FILE = Path(__file__).resolve().parent / "integration" / "docker-compose.yml"
REPO_ROOT = COMPOSE_FILE.parents[3]

UPDATE_TIMEOUT_SECONDS = 180
FP1 = "AAAA" * 10
FP2 = "BBBB" * 10


def _run(cmd: list[str], **kwargs) -> subprocess.CompletedProcess:
    return subprocess.run(cmd, capture_output=True, text=True, check=False, **kwargs)


def _compose(project: str, *args: str) -> subprocess.CompletedProcess:
    return _run(["docker", "compose", "-f", str(COMPOSE_FILE), "-p", project, *args])


def _exec(project: str, service: str, *cmd: str) -> subprocess.CompletedProcess:
    return _compose(project, "exec", "-T", service, *cmd)


def _nginx(project: str, *args: str) -> subprocess.CompletedProcess:
    """Run curl inside the nginx container (headers + body)."""
    return _exec(project, "nginx", "curl", "-si", *args)


def _nginx_body(project: str, url: str) -> str:
    """Fetch a URL inside the nginx container and return the body."""
    result = _exec(project, "nginx", "curl", "-s", url)
    assert result.returncode == 0, f"curl failed: {result.stderr}"
    return result.stdout


def _mysql(project: str, sql: str) -> str:
    result = _exec(project, "mariadb", "mariadb", "-utorstatus", "-ptorstatus", "torstatus", "-e", sql)
    assert result.returncode == 0, f"mysql failed: {result.stderr}"
    return result.stdout


def _docker_compose_available() -> bool:
    if shutil.which("docker") is None:
        return False
    return _run(["docker", "compose", "version"]).returncode == 0


@pytest.fixture(scope="module")
def testbed() -> Iterator[str]:
    """Bring up the compose testbed once for all tests in this module."""
    if not _docker_compose_available():
        pytest.skip("docker compose is not available.")

    # The web image needs composer dependencies mounted from ./nginx/vendor
    vendor_autoload = REPO_ROOT / "nginx" / "vendor" / "autoload.php"
    if not vendor_autoload.exists():
        if shutil.which("composer") is None:
            pytest.skip("nginx/vendor missing and composer not installed.")
        install = _run(["composer", "install", "--no-dev", "--no-interaction"], cwd=REPO_ROOT / "nginx")
        if install.returncode != 0:
            pytest.fail(f"composer install failed:\n{install.stdout}\n{install.stderr}")

    project = f"tsint{uuid.uuid4().hex[:8]}"
    up = _compose(project, "up", "--build", "-d")
    if up.returncode != 0:
        pytest.fail(f"compose up failed:\nSTDOUT:\n{up.stdout}\nSTDERR:\n{up.stderr}")

    try:
        yield project
    finally:
        _compose(project, "down", "-v", "--remove-orphans")


@pytest.fixture(scope="module", autouse=True)
def _wait_for_update(testbed: str) -> None:
    """Wait until the updater has completed its first cycle."""
    deadline = time.time() + UPDATE_TIMEOUT_SECONDS
    while time.time() < deadline:
        done = _exec(testbed, "updater", "test", "-f", "/opt/torstatus/last_update")
        if done.returncode == 0:
            return
        time.sleep(2)

    logs = _compose(testbed, "logs", "--no-color", "--tail=200", "updater")
    pytest.fail(
        "updater did not finish its first cycle in time.\n"
        f"updater logs:\n{logs.stdout}\n{logs.stderr}"
    )


def test_updater_completed(testbed: str) -> None:
    """The updater ran a full cycle and wrote the status tables."""
    logs = _compose(testbed, "logs", "--no-color", "updater")
    assert "Update completed" in logs.stdout + logs.stderr

    out = _mysql(testbed, "SELECT ActiveDescriptorTable FROM Status WHERE ID = 1")
    assert "Descriptor2" in out, f"expected flip to Descriptor2, got: {out}"


def test_descriptors_and_network_status_populated(testbed: str) -> None:
    count = _mysql(testbed, "SELECT COUNT(*) FROM NetworkStatus2")
    assert "2" in count

    names = _mysql(testbed, "SELECT Name FROM NetworkStatus2 ORDER BY Name")
    assert "FakeRelay1" in names and "FakeRelay2" in names


def test_ipv6_exit_policy_stored(testbed: str) -> None:
    """accept6/reject6 lines must survive into ExitPolicySERDATA (regression)."""
    out = _mysql(testbed, "SELECT ExitPolicySERDATA FROM Descriptor2 WHERE Fingerprint = '%s'" % FP1)
    assert "accept6" in out and "reject6" in out, f"missing IPv6 policy lines: {out}"


def test_ipv6_or_address_stored(testbed: str) -> None:
    out = _mysql(testbed, "SELECT address FROM ORAddresses2")
    assert "2001:db8::10" in out


def test_geoip_country_assigned(testbed: str) -> None:
    out = _mysql(testbed, "SELECT IP, CountryCode FROM NetworkStatus2 ORDER BY Name")
    assert "51.15.37.10" in out and "de" in out
    assert "198.51.100.20" in out and "us" in out


def test_index_renders_routers(testbed: str) -> None:
    result = _nginx(testbed, "http://localhost/index.php")
    assert "200 OK" in result.stdout.splitlines()[0]
    assert "FakeRelay1" in result.stdout
    assert "FakeRelay2" in result.stdout


def test_whois_redirect(testbed: str) -> None:
    result = _nginx(testbed, "http://localhost/whois.php?ip=51.15.37.10")
    head = result.stdout.splitlines()[0]
    assert "302" in head
    assert "client.rdap.org/?object=51.15.37.10" in result.stdout


def test_whois_bad_ip_rejected(testbed: str) -> None:
    result = _nginx(testbed, "http://localhost/whois.php?ip=not-an-ip")
    assert "400" in result.stdout.splitlines()[0]


def test_router_detail(testbed: str) -> None:
    result = _nginx(testbed, f"http://localhost/router_detail.php?FP={FP1}")
    assert "200" in result.stdout.splitlines()[0]
    assert "FakeRelay1" in result.stdout


def test_router_detail_unknown_fp_404(testbed: str) -> None:
    result = _nginx(testbed, "http://localhost/router_detail.php?FP=%s" % ("C" * 40))
    assert "404" in result.stdout.splitlines()[0]


def test_csv_export_attachment(testbed: str) -> None:
    result = _nginx(testbed, "http://localhost/query_export.php")
    assert "Content-Disposition: attachment" in result.stdout
    assert "FakeRelay1" in result.stdout


def test_bandwidth_history_graph_json(testbed: str) -> None:
    body = _nginx_body(testbed, f"http://localhost/graphs.php?type=bandwidth_history&MODE=ReadHistory&FP={FP1}")
    payload = json.loads(body)
    assert payload["data"], "expected non-empty history data"


def test_ip_lists(testbed: str) -> None:
    result = _nginx(testbed, "http://localhost/ip_list_all.php")
    assert "51.15.37.10" in result.stdout
    assert "2001:db8::10" in result.stdout
