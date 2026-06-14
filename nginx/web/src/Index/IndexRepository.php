<?php

declare(strict_types=1);

namespace TorStatus\Index;

final class IndexRepository
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

    public function countRouters(): int
    {
        $record = $this->cachedSingleRow("select count(*) as Count from {$this->tables->networkStatus}", 1800);
        return (int)($record['Count'] ?? 0);
    }

    public function countDescriptors(): int
    {
        $record = $this->cachedSingleRow("select count(*) as Count from {$this->tables->descriptor}", 1800);
        return (int)($record['Count'] ?? 0);
    }

    /** @return array<string, mixed> */
    public function fetchNetworkStatusSource(): array
    {
        $query = "select Name, IP, ORPort, DirPort, Fingerprint, Platform, LastDescriptorPublished, OnionKey, SigningKey, Contact, DescriptorSignature from NetworkStatusSource where ID = 1";
        return $this->cachedSingleRow($query, 1800) ?: [];
    }

    /** @return array<string, mixed> */
    public function fetchNetworkStatusSourceLocation(string $fingerprint): array
    {
        $fingerprint = $this->mysqli->real_escape_string($fingerprint);
        $query = "select Hostname, CountryCode from {$this->tables->networkStatus} where Fingerprint = '$fingerprint'";
        return $this->cachedSingleRow($query, 1800) ?: [];
    }

    public function countExitRoutersByIp(string $remoteIp): int
    {
        $remoteIp = $this->mysqli->real_escape_string($remoteIp);
        $record = $this->cachedSingleRow("select count(*) as Count from {$this->tables->networkStatus} where IP = '$remoteIp' and FExit = 1", 1800);
        $count = (int)($record['Count'] ?? 0);
        if ($count > 0) {
            return $count;
        }

        $query = "select count(*) as Count
            from {$this->tables->orAddresses}
                join {$this->tables->descriptor} on {$this->tables->descriptor}.ID = {$this->tables->orAddresses}.descriptor_id
                join {$this->tables->networkStatus} on {$this->tables->networkStatus}.Fingerprint = {$this->tables->descriptor}.Fingerprint
            where address = '$remoteIp'
                and {$this->tables->networkStatus}.FExit = 1";
        $record = $this->cachedSingleRow($query, 1800);

        return (int)($record['Count'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchExitPolicyCandidates(string $remoteIp): array
    {
        $remoteIp = $this->mysqli->real_escape_string($remoteIp);
        $query = "select {$this->tables->networkStatus}.Name Name, {$this->tables->networkStatus}.Fingerprint Fingerprint, {$this->tables->descriptor}.ExitPolicySERDATA ExitPolicySERDATA
            from {$this->tables->networkStatus}
                inner join {$this->tables->descriptor} on {$this->tables->networkStatus}.Fingerprint = {$this->tables->descriptor}.Fingerprint
                left join {$this->tables->orAddresses} on {$this->tables->descriptor}.ID = {$this->tables->orAddresses}.descriptor_id
            where ({$this->tables->networkStatus}.IP = '$remoteIp' or {$this->tables->orAddresses}.address = '$remoteIp')
            group by Name, Fingerprint, ExitPolicySERDATA";

        $result = $this->mysqli->query($query);
        if (!$result) {
            \die_503('Query failed: ' . $this->mysqli->error);
        }

        $rows = [];
        while ($record = $result->fetch_assoc()) {
            $rows[] = $record;
        }
        $result->free();

        return $rows;
    }

    /** @return array<string, mixed> */
    public function fetchAggregateStats(): array
    {
        $query = "select
            (select count(*) from {$this->tables->networkStatus}) as 'Total',
            (select count(*) from {$this->tables->networkStatus} where FAuthority = '1') as 'Authority',
            (select count(*) from {$this->tables->networkStatus} where FBadDirectory = '1') as 'BadDirectory',
            (select count(*) from {$this->tables->networkStatus} where FBadExit = '1') as 'BadExit',
            (select count(*) from {$this->tables->networkStatus} where FExit = '1') as 'Exit',
            (select count(*) from {$this->tables->networkStatus} where FFast = '1') as 'Fast',
            (select count(*) from {$this->tables->networkStatus} where FGuard = '1') as 'Guard',
            (select count(*) from {$this->tables->descriptor} inner join {$this->tables->networkStatus} on {$this->tables->networkStatus}.Fingerprint = {$this->tables->descriptor}.Fingerprint where Hibernating = '1') as 'Hibernating',
            (select count(*) from {$this->tables->networkStatus} where FNamed = '1') as 'Named',
            (select count(*) from {$this->tables->networkStatus} where FStable = '1') as 'Stable',
            (select count(*) from {$this->tables->networkStatus} where FRunning = '1') as 'Running',
            (select count(*) from {$this->tables->networkStatus} where FValid = '1') as 'Valid',
            (select count(*) from {$this->tables->networkStatus} where FV2Dir = '1') as 'V2Dir',
            (select count(*) from {$this->tables->networkStatus} where FHSDir = '1') as 'HSDir',
            (select count(*) from {$this->tables->networkStatus} where DirPort > 0) as 'DirMirror'";

        return $this->cachedSingleRow($query, 1800) ?: [];
    }

    public function fetchRouterPage(IndexRequest $request): RouterPage
    {
        $baseQuery = $this->buildRouterQuery($request, false);
        $orderedQuery = $this->appendOrderBy($baseQuery, $request);

        $countQuery = "SELECT COUNT(*) AS Count FROM ($baseQuery) AS countQuery";
        $countResult = $this->mysqli->query($countQuery);
        if (!$countResult) {
            \die_503('Count query failed: ' . $this->mysqli->error);
        }
        $countRecord = $countResult->fetch_assoc();
        $totalResults = (int)($countRecord['Count'] ?? 0);
        $countResult->free();

        $totalPages = max(1, (int)ceil($totalResults / $request->rowsPerPage));
        $page = min($request->page, $totalPages);
        $offset = ($page - 1) * $request->rowsPerPage;

        $query = $orderedQuery . ' LIMIT ' . $request->rowsPerPage . ' OFFSET ' . $offset;
        $result = $this->mysqli->query($query);
        if (!$result) {
            \die_503('Query failed: ' . $this->mysqli->error);
        }

        return new RouterPage($result, $totalResults, $totalPages, $page);
    }

    public function fetchRouterExport(IndexRequest $request): \mysqli_result
    {
        $query = $this->appendOrderBy($this->buildRouterQuery($request, false), $request);
        $result = $this->mysqli->query($query);
        if (!$result) {
            \die_503('Export query failed: ' . $this->mysqli->error);
        }

        return $result;
    }

    public function countRoutersByIp(string $ip): int
    {
        $ip = $this->mysqli->real_escape_string($ip);
        $record = $this->cachedSingleRow("select count(*) as Count from {$this->tables->networkStatus} where IP = '$ip'", 1800);

        return (int)($record['Count'] ?? 0);
    }

    /** @return array<int, array{name: string, fingerprint: string, exitPolicy: array<int, string>|null}> */
    public function fetchRoutersByIp(string $ip, bool $includeExitPolicy): array
    {
        $ip = $this->mysqli->real_escape_string($ip);
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;

        if ($includeExitPolicy) {
            $query = "select $networkStatus.Name, $networkStatus.Fingerprint, $descriptor.ExitPolicySERDATA
                from $networkStatus
                    inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint
                where $networkStatus.IP = '$ip'";
        } else {
            $query = "select $networkStatus.Name, $networkStatus.Fingerprint
                from $networkStatus
                where $networkStatus.IP = '$ip'";
        }

        $result = $this->mysqli->query($query);
        if (!$result) {
            \die_503('Query failed: ' . $this->mysqli->error);
        }

        $rows = [];
        while ($record = $result->fetch_assoc()) {
            $exitPolicy = null;
            if ($includeExitPolicy) {
                $unserialized = unserialize((string)($record['ExitPolicySERDATA'] ?? ''), ['allowed_classes' => false]);
                $exitPolicy = is_array($unserialized) ? array_values(array_filter($unserialized, 'is_string')) : [];
            }
            $rows[] = [
                'name' => (string)($record['Name'] ?? ''),
                'fingerprint' => (string)($record['Fingerprint'] ?? ''),
                'exitPolicy' => $exitPolicy,
            ];
        }
        $result->free();

        return $rows;
    }

    /** @return array<int, array{label: string, count: int, percentage: float}> */
    public function buildStatsRows(array $aggregateStats, int $routerCount, int $currentResultSet): array
    {
        if ($routerCount === 0) {
            return [];
        }

        $stats = [
            ['Total Number of Routers:', $routerCount],
            ['Routers in Current Query Result Set:', $currentResultSet],
            ["Total Number of 'Authority' Routers:", (int)($aggregateStats['Authority'] ?? 0)],
            ["Total Number of 'Bad Directory' Routers:", (int)($aggregateStats['BadDirectory'] ?? 0)],
            ["Total Number of 'Bad Exit' Routers:", (int)($aggregateStats['BadExit'] ?? 0)],
            ["Total Number of 'Exit' Routers:", (int)($aggregateStats['Exit'] ?? 0)],
            ["Total Number of 'Fast' Routers:", (int)($aggregateStats['Fast'] ?? 0)],
            ["Total Number of 'Guard' Routers:", (int)($aggregateStats['Guard'] ?? 0)],
            ["Total Number of 'Hibernating' Routers:", (int)($aggregateStats['Hibernating'] ?? 0)],
            ["Total Number of 'Named' Routers:", (int)($aggregateStats['Named'] ?? 0)],
            ["Total Number of 'Stable' Routers:", (int)($aggregateStats['Stable'] ?? 0)],
            ["Total Number of 'Running' Routers:", (int)($aggregateStats['Running'] ?? 0)],
            ["Total Number of 'Valid' Routers:", (int)($aggregateStats['Valid'] ?? 0)],
            ["Total Number of 'V2Dir' Routers:", (int)($aggregateStats['V2Dir'] ?? 0)],
            ["Total Number of 'HSDir' Routers:", (int)($aggregateStats['HSDir'] ?? 0)],
            ["Total Number of 'Directory Mirror' Routers:", (int)($aggregateStats['DirMirror'] ?? 0)],
        ];

        $rows = [];
        foreach ($stats as $stat) {
            $count = (int)$stat[1];
            $rows[] = [
                'label' => (string)$stat[0],
                'count' => $count,
                'percentage' => round(($count / $routerCount) * 100, 2),
            ];
        }

        return $rows;
    }

    private function buildRouterQuery(IndexRequest $request, bool $includeOrder): string
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;

        $query = "select $networkStatus.Name, $networkStatus.Fingerprint";
        $query .= ", $networkStatus.CountryCode";
        $query .= ", floor($descriptor.BandwidthOBSERVED / 1024) as Bandwidth";
        $query .= ", floor(((UNIX_TIMESTAMP() - (UNIX_TIMESTAMP($descriptor.LastDescriptorPublished) + {$this->offsetFromGmt})) + CAST($descriptor.Uptime AS DECIMAL)) / 3600) as Uptime";
        $query .= ", $descriptor.LastDescriptorPublished";
        $query .= ", $networkStatus.Hostname";
        $query .= ", $networkStatus.IP";
        $query .= ", $networkStatus.ORPort";
        $query .= ", $networkStatus.DirPort";
        $query .= ", $descriptor.Platform";
        $query .= ", $descriptor.Contact";
        $query .= ", $networkStatus.FAuthority as Authority";
        $query .= ", $networkStatus.FBadDirectory as BadDir";
        $query .= ", $networkStatus.FBadExit as BadExit";
        $query .= ", $networkStatus.FExit as 'Exit'";
        $query .= ", $networkStatus.FFast as Fast";
        $query .= ", $networkStatus.FGuard as Guard";
        $query .= ", $descriptor.Hibernating as 'Hibernating'";
        $query .= ", $networkStatus.FNamed as Named";
        $query .= ", $networkStatus.FStable as Stable";
        $query .= ", $networkStatus.FRunning as Running";
        $query .= ", $networkStatus.FValid as Valid";
        $query .= ", $networkStatus.FV2Dir as V2Dir";
        $query .= ", $networkStatus.FHSDir as HSDir";
        $query .= ", INET_ATON($networkStatus.IP) as NIP from $networkStatus inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint";

        $where = $this->buildWhereClauses($request);
        if ($where !== []) {
            $query .= ' where ' . implode(' and ', $where);
        }

        $query .= " group by $descriptor.Fingerprint";

        return $includeOrder ? $this->appendOrderBy($query, $request) : $query;
    }

    /** @return array<int, string> */
    private function buildWhereClauses(IndexRequest $request): array
    {
        $where = [];
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $filterColumns = [
            'FAuthority' => "$networkStatus.FAuthority",
            'FBadDirectory' => "$networkStatus.FBadDirectory",
            'FBadExit' => "$networkStatus.FBadExit",
            'FExit' => "$networkStatus.FExit",
            'FFast' => "$networkStatus.FFast",
            'FGuard' => "$networkStatus.FGuard",
            'FHibernating' => "$descriptor.Hibernating",
            'FNamed' => "$networkStatus.FNamed",
            'FStable' => "$networkStatus.FStable",
            'FRunning' => "$networkStatus.FRunning",
            'FValid' => "$networkStatus.FValid",
            'FV2Dir' => "$networkStatus.FV2Dir",
            'FHSDir' => "$networkStatus.FHSDir",
        ];

        foreach ($filterColumns as $filterName => $column) {
            $filterValue = $request->filters[$filterName] ?? 'OFF';
            if ($filterValue !== 'OFF') {
                $where[] = "$column = $filterValue";
            }
        }

        $searchPredicate = $this->buildSearchPredicate($request);
        if ($searchPredicate !== null) {
            $where[] = $searchPredicate;
        }

        return $where;
    }

    private function buildSearchPredicate(IndexRequest $request): ?string
    {
        if ($request->customSearchInput === null) {
            return null;
        }

        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $fieldMap = [
            'Fingerprint' => "$networkStatus.Fingerprint",
            'Name' => "$networkStatus.Name",
            'CountryCode' => "$networkStatus.CountryCode",
            'Bandwidth' => "floor($descriptor.BandwidthOBSERVED / 1024)",
            'Uptime' => "floor($descriptor.Uptime / 3600)",
            'LastDescriptorPublished' => "$networkStatus.LastDescriptorPublished",
            'IP' => "$networkStatus.IP",
            'Hostname' => "$networkStatus.Hostname",
            'ORPort' => "$networkStatus.ORPort",
            'DirPort' => "$networkStatus.DirPort",
            'Platform' => "$descriptor.Platform",
            'Contact' => "$descriptor.Contact",
        ];

        $numericFields = ['Bandwidth', 'Uptime', 'ORPort', 'DirPort'];
        $field = $request->customSearchField;
        $value = $request->customSearchInput;
        if (in_array($field, $numericFields, true) && !is_numeric($value)) {
            $value = '0';
        }

        $escapedValue = $this->mysqli->real_escape_string($value);
        $column = $fieldMap[$field] ?? $fieldMap['Fingerprint'];

        switch ($request->customSearchModifier) {
            case 'Contains':
                return "$column like '%$escapedValue%'";
            case 'LessThan':
                return "$column < '$escapedValue'";
            case 'GreaterThan':
                return "$column > '$escapedValue'";
            case 'Equals':
            default:
                return "$column = '$escapedValue'";
        }
    }

    private function appendOrderBy(string $query, IndexRequest $request): string
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $sortRequest = $request->sortRequest;
        $sortOrder = $request->sortOrder;

        $fieldMap = [
            'Fingerprint' => "$networkStatus.Fingerprint",
            'LastDescriptorPublished' => "$networkStatus.LastDescriptorPublished",
            'ORPort' => "$networkStatus.ORPort",
            'DirPort' => "$networkStatus.DirPort",
            'FAuthority' => "$networkStatus.FAuthority",
            'FBadDirectory' => "$networkStatus.FBadDirectory",
            'FBadExit' => "$networkStatus.FBadExit",
            'FExit' => "$networkStatus.FExit",
            'FFast' => "$networkStatus.FFast",
            'FGuard' => "$networkStatus.FGuard",
            'Hibernating' => "$descriptor.Hibernating",
            'FNamed' => "$networkStatus.FNamed",
            'FStable' => "$networkStatus.FStable",
            'FRunning' => "$networkStatus.FRunning",
            'FValid' => "$networkStatus.FValid",
            'FV2Dir' => "$networkStatus.FV2Dir",
            'FHSDir' => "$networkStatus.FHSDir",
        ];

        if ($sortRequest === 'Name') {
            return $query . " order by Name $sortOrder";
        }
        if ($sortRequest === 'IP') {
            return $query . " order by NIP $sortOrder, Name Asc";
        }
        if (isset($fieldMap[$sortRequest])) {
            return $query . " order by {$fieldMap[$sortRequest]} $sortOrder";
        }

        return $query . " order by $sortRequest $sortOrder, Name Asc";
    }

    /** @return array<string, mixed> */
    private function cachedSingleRow(string $query, int $cacheExpiration): array
    {
        $record = \db_query_single_row($query, $cacheExpiration);
        return is_array($record) ? $record : [];
    }
}
