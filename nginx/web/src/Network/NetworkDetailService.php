<?php

declare(strict_types=1);

namespace TorStatus\Network;

use TorStatus\Database\QueryExecutor;
use TorStatus\Graph\GraphSessionStore;
use TorStatus\Index\TableNames;

final class NetworkDetailService
{
    /** @var QueryExecutor */
    private $db;

    /** @var TableNames */
    private $tables;

    /** @var int */
    private $offsetFromGmt;

    public function __construct(QueryExecutor $db, TableNames $tables, int $offsetFromGmt)
    {
        $this->db = $db;
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
        $record = $this->db->singleRow("select count(*) as Count from {$this->tables->networkStatus}", [], 1800);
        return (int)($record['Count'] ?? 0);
    }

    /** @return array{labels: array<int, string>, data: array<int, int>} */
    private function countryCodeGraph(bool $exitOnly): array
    {
        $where = $exitOnly ? ' where FExit = ?' : '';
        $params = $exitOnly ? [1] : [];
        $query = "select CountryCode, count(CountryCode) as Count from {$this->tables->networkStatus}{$where} group by CountryCode";
        $result = $this->db->result($query, $params);

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
        $elapsedSeconds = "CAST(((UNIX_TIMESTAMP() - (UNIX_TIMESTAMP($descriptor.LastDescriptorPublished) + ?)) + $descriptor.Uptime) AS SIGNED)";
        $weeksRunning = "floor(($elapsedSeconds / 86400) / 7)";
        $query = "select $weeksRunning as WeeksRunning, count($weeksRunning) as Count
            from $descriptor
                inner join $networkStatus on $descriptor.Fingerprint = $networkStatus.Fingerprint
            group by WeeksRunning";
        $result = $this->db->result($query, [$this->offsetFromGmt, $this->offsetFromGmt]);

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
        $query = "select
            sum(case when $bandwidth between 0 and 10 then 1 else 0 end) as b0,
            sum(case when $bandwidth between 11 and 20 then 1 else 0 end) as b1,
            sum(case when $bandwidth between 21 and 50 then 1 else 0 end) as b2,
            sum(case when $bandwidth between 51 and 100 then 1 else 0 end) as b3,
            sum(case when $bandwidth between 101 and 500 then 1 else 0 end) as b4,
            sum(case when $bandwidth between 501 and 1000 then 1 else 0 end) as b5,
            sum(case when $bandwidth between 1001 and 2000 then 1 else 0 end) as b6,
            sum(case when $bandwidth between 2001 and 3000 then 1 else 0 end) as b7,
            sum(case when $bandwidth between 3001 and 5000 then 1 else 0 end) as b8,
            sum(case when $bandwidth > 5000 then 1 else 0 end) as b9
            from $descriptor
                inner join $networkStatus on $descriptor.Fingerprint = $networkStatus.Fingerprint";
        $record = $this->db->singleRow($query, [], 1800);

        return [
            (int)($record['b0'] ?? 0),
            (int)($record['b1'] ?? 0),
            (int)($record['b2'] ?? 0),
            (int)($record['b3'] ?? 0),
            (int)($record['b4'] ?? 0),
            (int)($record['b5'] ?? 0),
            (int)($record['b6'] ?? 0),
            (int)($record['b7'] ?? 0),
            (int)($record['b8'] ?? 0),
            (int)($record['b9'] ?? 0),
        ];
    }

    /** @return array<int, int> */
    private function platformGraph(int $routerCount): array
    {
        $descriptor = $this->tables->descriptor;
        $networkStatus = $this->tables->networkStatus;
        $query = "select
            sum(case when Platform like ? then 1 else 0 end) as FreeBSD,
            sum(case when Platform like ? then 1 else 0 end) as Linux,
            sum(case when Platform like ? or Platform like ? then 1 else 0 end) as Macintosh,
            sum(case when Platform like ? then 1 else 0 end) as NetBSD,
            sum(case when Platform like ? then 1 else 0 end) as OpenBSD,
            sum(case when Platform like ? then 1 else 0 end) as SunOS,
            sum(case when Platform like ? then 1 else 0 end) as Windows
            from $networkStatus inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint";

        $record = $this->db->singleRow($query, ['%freebsd%', '%linux%', '%macintosh%', '%darwin%', '%netbsd%', '%openbsd%', '%sunos%', '%windows%'], 1800);
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
            (select count(*) from $networkStatus) as 'Total',
            (select count(*) from $networkStatus where FAuthority = ?) as 'Authority',
            (select count(*) from $networkStatus where FBadDirectory = ?) as 'BadDirectory',
            (select count(*) from $networkStatus where FBadExit = ?) as 'BadExit',
            (select count(*) from $networkStatus where FExit = ?) as 'Exit',
            (select count(*) from $networkStatus where FFast = ?) as 'Fast',
            (select count(*) from $networkStatus where FGuard = ?) as 'Guard',
            (select count(*) from $descriptor inner join $networkStatus on $networkStatus.Fingerprint = $descriptor.Fingerprint where Hibernating = ?) as 'Hibernating',
            (select count(*) from $networkStatus where FNamed = ?) as 'Named',
            (select count(*) from $networkStatus where FStable = ?) as 'Stable',
            (select count(*) from $networkStatus where FRunning = ?) as 'Running',
            (select count(*) from $networkStatus where FValid = ?) as 'Valid',
            (select count(*) from $networkStatus where FV2Dir = ?) as 'V2Dir',
            (select count(*) from $networkStatus where DirPort > ?) as 'DirMirror'";

        $record = $this->db->singleRow($query, [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0], 1800);
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
}
