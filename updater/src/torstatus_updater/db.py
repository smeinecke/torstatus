"""MariaDB helpers and table-flipping logic."""

import logging

import pymysql

LOG = logging.getLogger(__name__)


class Database:
    """Wraps a MariaDB connection with helpers for the TorStatus schema."""

    def __init__(
        self,
        host: str,
        user: str,
        password: str,
        database: str,
    ) -> None:
        """Open a connection to the MariaDB server."""
        self.host = host
        self.user = user
        self.password = password
        self.database = database
        self._conn = pymysql.connect(
            host=host,
            user=user,
            password=password,
            database=database,
            autocommit=False,
            cursorclass=pymysql.cursors.Cursor,
        )

    def cursor(self) -> pymysql.cursors.Cursor:
        """Return a new cursor from the connection."""
        return self._conn.cursor()

    def commit(self) -> None:
        """Commit the current transaction."""
        self._conn.commit()

    def close(self) -> None:
        """Close the database connection."""
        self._conn.close()

    def check_installed(self) -> bool:
        """Ensure the Status table has at least one row."""
        with self.cursor() as cur:
            cur.execute("SELECT count(*) FROM Status")
            row = cur.fetchone()
            return row[0] >= 1 if row else False

    def active_tables(self) -> tuple[int, str, str, str, str]:
        """Return the *next* descriptor table number and table names.

        We flip between 1 and 2 so the web frontend can read from the
        previously-populated set while we build the new one.
        """
        with self.cursor() as cur:
            cur.execute("SELECT ActiveNetworkStatusTable, ActiveDescriptorTable FROM Status WHERE ID = 1")
            row = cur.fetchone()
        descriptor_table = 2 if row and row[0] and "1" in str(row[0]) else 1
        return (
            descriptor_table,
            f"Descriptor{descriptor_table}",
            f"NetworkStatus{descriptor_table}",
            f"ORAddresses{descriptor_table}",
            f"Bandwidth{descriptor_table}",
        )

    def truncate_staging(self, descriptor_table: int) -> None:
        """Truncate the tables we are about to repopulate."""
        for suffix in (
            f"Bandwidth{descriptor_table}",
            f"Descriptor{descriptor_table}",
            f"ORAddresses{descriptor_table}",
            f"NetworkStatus{descriptor_table}",
        ):
            with self.cursor() as cur:
                LOG.debug("Truncating %s", suffix)
                cur.execute(f"TRUNCATE TABLE {suffix}")  # nosec B608

    def fix_future_timestamps(self, descriptor_table: int) -> None:
        """Clamp LastDescriptorPublished to NOW() where it lies in the future."""
        with self.cursor() as cur:
            for tbl in (f"Descriptor{descriptor_table}", f"NetworkStatus{descriptor_table}"):
                cur.execute(f"UPDATE {tbl} SET LastDescriptorPublished = NOW() WHERE LastDescriptorPublished > NOW()")  # nosec B608

    def update_network_status_source(
        self,
        descriptor_table: int,
        nickname: str,
        source_fingerprint: str | None,
    ) -> None:
        """Populate NetworkStatusSource from the newly-built Descriptor table."""
        with self.cursor() as cur:
            cur.execute("TRUNCATE TABLE NetworkStatusSource")
            if source_fingerprint:
                cur.execute(
                    f"INSERT INTO NetworkStatusSource SELECT * FROM Descriptor{descriptor_table} WHERE Fingerprint = %s LIMIT 1",  # nosec B608
                    (source_fingerprint,),
                )
            else:
                cur.execute(
                    f"INSERT INTO NetworkStatusSource SELECT * FROM Descriptor{descriptor_table} WHERE Name = %s LIMIT 1",  # nosec B608
                    (nickname,),
                )
            cur.execute("UPDATE NetworkStatusSource SET ID = 1")

    def finalize(
        self,
        descriptor_table: int,
        elapsed: int,
    ) -> None:
        """Update the Status row to point readers at the new tables."""
        with self.cursor() as cur:
            cur.execute(
                "UPDATE Status SET LastUpdate = NOW(), LastUpdateElapsed = %s, "
                "ActiveNetworkStatusTable = %s, ActiveDescriptorTable = %s, "
                "ActiveORAddressesTable = %s WHERE ID = 1",
                (
                    elapsed,
                    f"NetworkStatus{descriptor_table}",
                    f"Descriptor{descriptor_table}",
                    f"ORAddresses{descriptor_table}",
                ),
            )
