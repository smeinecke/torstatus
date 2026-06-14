<?php

declare(strict_types=1);

namespace TorStatus\Network;

use TorStatus\Graph\GraphSessionStore;
use TorStatus\Index\TableNames;

final class NetworkDetailService
{
    /** @var \mysqli */
    private $mysqli;

    /** @var TableNames */
    private $tables;

    /** @var int */
    private $offsetFromGmt;

    public function __construct(\mysqli $mysqli, TableNames $tables, int $offsetFromGmt)
    {
        $this->mysqli = $mysqli;
        $this->tables = $tables;
        $this->offsetFromGmt = $offsetFromGmt;
    }

    /** @param array<string, mixed> $session */
    public function prepareGraphs(array &$session): void
    {
        $routerCount = $this->routerCount();

        $country = $this->countryCodeGraph(false);
        GraphSessionStore::put($session, 'CCGraph', $country['data'], $country['labels'], 'Number of Routers by Country Code');

        $exitCountry = $this->countryCodeGraph(true);
        GraphSessionStore::put($session, 'CCExitGraph', $exitCountry['data'], $exitCountry['labels'], 'Number of Exit Routers by Country Code');

        $uptime = $this->uptimeGraph();
        GraphSessionStore::put($session, 'UptimeGraph', $uptime['data'], $uptime['labels'], 'Number of Routers by Time Running (Weeks)');

        GraphSessionStore::put(
            $session,
            'BWGraph',
            $this->bandwidthGraph(),
            ['0-10', '11-20', '21-50', '51-100', '101-500', '501-1000', '1001-2000', '2001-3000', '3001-5000', '5001+'],
            'Number of Routers by Observed Bandwidth (KB/s)'
        );

        GraphSessionStore::put(
            $session,
            'PlatformGraph',
            $this->platformGraph($routerCount),
            ['Unknown', 'FreeBSD', 'Linux', 'Macintosh', 'NetBSD', 'OpenBSD', 'SunOS', 'Windows'],
            'Number of Routers by Platform'
        );

        GraphSessionStore::put(
            $session,
            'SummaryGraph',
            $this->summaryGraph(),
            ['Total', 'Authority', 'BadDirectory', 'BadExit', 'Exit', 'Fast', 'Guard', 'Hibernating', 'Named', 'Stable', 'Running', 'Valid', 'V2Dir', 'Dir. Mirror'],
            'Aggregate Summary -- Number of Routers Matching Specified Criteria'
        );
    }

    private function routerCount(): int
    {
        $record = $this->singleRow("select count(*) as Count from {$this->tables->networkStatus}");
        return (int)($record['Count'] ?? 0);
    }

    /** @return array{labels: array<int, string>, data: array<int, int>} */
    private function countryCodeGraph(bool $exitOnly): array
    {
        $where = $exitOnly ? " where FExit = '1'" : '';
        $query = "select CountryCode, count(CountryCode) as Count from {$this->tables->networkStatus}{$where} group by CountryCode";
        $result = $this->query($query);

        $labels = [];
        $data = [];
        while ($record = $result->fetch_assoc()) {
            $labels[] = $record['CountryCode'] ?: 'N/A';
            $data[] = (int)$record['Count'];
        }
        $result->free();

        return ['labels' => $labels, 'data' => $data];
    }

    /** @return array{labels: array<int, int>, data: array<int, int>} */
    private function uptimeGraph(): array
    {
        $descriptor = $this->tables->descriptor;
        $networkStatus = $this->tables->networkStatus;
        $elapsedSeconds = "CAST(((UNIX_TIMESTAMP() - (UNIX_TIMESTAMP($descriptor.LastDescriptorPublished) + {$this->offsetFromGmt})) + $descriptor.Uptime) AS SIGNED)";
        $weeksRunning = "floor(($elapsedSeconds / 86400) / 7)";
        $query = "select $weeksRunning as WeeksRunning, count($weeksRunning) as Count from $descriptor inner join $networkStatus on $descriptor.Fingerprint = $networkStatus.Fingerprint group by WeeksRunning";
        $result = $this->query($query);

        $labels = [];
        $data = [];
        while ($record = $result->fetch_assoc()) {
            if ((int)$record['WeeksRunning'] > -1) {
                $labels[] = (int)$record['WeeksRunning'];
                $data[] = (int)$record['Count'];
            }
        }
        $result->free();

        return ['labels' => $labels, 'data' => $data];
    }

    /** @return array<int, int> */
    private function bandwidthGraph(): array
    {
        $descriptor = $this->tables->descriptor;
        $networkStatus = $this->tables->networkStatus;
        $bandwidth = "floor($descriptor.BandwidthOBSERVED / 1024)";
        $query = "select $bandwidth as Bandwidth, count($bandwidth) as Number from $descriptor inner join $networkStatus on $descriptor.Fingerprint = $networkStatus.Fingerprint group by Bandwidth";
        $result = $this->query($query);

        $buckets = array_fill(0, 10, 0);
        while ($record = $result->fetch_assoc()) {
            $bucket = $this->bandwidthBucket((int)$record['Bandwidth']);
            if ($bucket !== null) {
                $buckets[$bucket] += (int)$record['Number'];
            }
        }
        $result->free();

        return $buckets;
    }

    private function bandwidthBucket(int $bandwidth): ?int
    {
        if ($bandwidth < 0) {
            return null;
        }
        if ($bandwidth <= 10) {
            return 0;
        }
        if ($bandwidth <= 20) {
            return 1;
        }
        if ($bandwidth <= 50) {
            return 2;
        }
        if ($bandwidth <= 100) {
            return 3;
        }
        if ($bandwidth <= 500) {
            return 4;
        }
        if ($bandwidth <= 1000) {
            return 5;
        }
        if ($bandwidth <= 2000) {
            return 6;
        }
        if ($bandwidth <= 3000) {
            return 7;
        }
        if ($bandwidth <= 5000) {
            return 8;
        }
        return 9;
    }

    /** @return array<int, int> */
    private function platformGraph(int $routerCount): array
    {
        $descriptor = $this->tables->descriptor;
        $networkStatus = $this->tables->networkStatus;
        $query = "select
            sum(case when Platform like '%freebsd%' then 1 else 0 end) as FreeBSD,
            sum(case when Platform like '%linux%' then 1 else 0 end) as Linux,
            sum(case when Platform like '%macintosh%' or Platform like '%darwin%' then 1 else 0 end) as Macintosh,
            sum(case when Platform like '%netbsd%' then 1 else 0 end) as NetBSD,
            sum(case when Platform like '%openbsd%' then 1 else 0 end) as OpenBSD,
            sum(case when Platform like '%sunos%' then 1 else 0 end) as SunOS,
            sum(case when Platform like '%windows%' then 1 else 0 end) as Windows
            from $networkStatus inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint";

        $record = $this->singleRow($query);
        $known = [
            (int)($record['FreeBSD'] ?? 0),
            (int)($record['Linux'] ?? 0),
            (int)($record['Macintosh'] ?? 0),
            (int)($record['NetBSD'] ?? 0),
            (int)($record['OpenBSD'] ?? 0),
            (int)($record['SunOS'] ?? 0),
            (int)($record['Windows'] ?? 0),
        ];

        return array_merge([max(0, $routerCount - array_sum($known))], $known);
    }

    /** @return array<int, int> */
    private function summaryGraph(): array
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $query = "select
            (select count(*) from $networkStatus) as Total,
            (select count(*) from $networkStatus where FAuthority = '1') as Authority,
            (select count(*) from $networkStatus where FBadDirectory = '1') as BadDirectory,
            (select count(*) from $networkStatus where FBadExit = '1') as BadExit,
            (select count(*) from $networkStatus where FExit = '1') as Exit,
            (select count(*) from $networkStatus where FFast = '1') as Fast,
            (select count(*) from $networkStatus where FGuard = '1') as Guard,
            (select count(*) from $descriptor inner join $networkStatus on $networkStatus.Fingerprint = $descriptor.Fingerprint where Hibernating = '1') as Hibernating,
            (select count(*) from $networkStatus where FNamed = '1') as Named,
            (select count(*) from $networkStatus where FStable = '1') as Stable,
            (select count(*) from $networkStatus where FRunning = '1') as Running,
            (select count(*) from $networkStatus where FValid = '1') as Valid,
            (select count(*) from $networkStatus where FV2Dir = '1') as V2Dir,
            (select count(*) from $networkStatus where DirPort > 0) as DirMirror";

        $record = $this->singleRow($query);
        return [
            (int)($record['Total'] ?? 0),
            (int)($record['Authority'] ?? 0),
            (int)($record['BadDirectory'] ?? 0),
            (int)($record['BadExit'] ?? 0),
            (int)($record['Exit'] ?? 0),
            (int)($record['Fast'] ?? 0),
            (int)($record['Guard'] ?? 0),
            (int)($record['Hibernating'] ?? 0),
            (int)($record['Named'] ?? 0),
            (int)($record['Stable'] ?? 0),
            (int)($record['Running'] ?? 0),
            (int)($record['Valid'] ?? 0),
            (int)($record['V2Dir'] ?? 0),
            (int)($record['DirMirror'] ?? 0),
        ];
    }

    private function query(string $query): \mysqli_result
    {
        $result = $this->mysqli->query($query);
        if (!$result) {
            \die_503('Query failed: ' . $this->mysqli->error);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function singleRow(string $query): array
    {
        $record = \db_query_single_row($query, 1800);
        return is_array($record) ? $record : [];
    }
}
