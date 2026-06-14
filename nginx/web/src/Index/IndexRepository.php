<?php

declare(strict_types=1);

namespace TorStatus\Index;

use TorStatus\Database\QueryExecutor;
use TorStatus\Network\IpAddress;

final class IndexRepository
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

    public function countRouters(): int
    {
        $record = $this->db->singleRow("select count(*) as Count from {$this->tables->networkStatus}", [], 1800);
        return (int)($record['Count'] ?? 0);
    }

    public function countDescriptors(): int
    {
        $record = $this->db->singleRow("select count(*) as Count from {$this->tables->descriptor}", [], 1800);
        return (int)($record['Count'] ?? 0);
    }

    /** @return array<string, mixed> */
    public function fetchNetworkStatusSource(): array
    {
        $query = 'select Name, IP, ORPort, DirPort, Fingerprint, Platform, LastDescriptorPublished, OnionKey, SigningKey, Contact, DescriptorSignature from NetworkStatusSource where ID = ?';
        return $this->db->singleRow($query, [1], 1800);
    }

    /** @return array<string, mixed> */
    public function fetchNetworkStatusSourceLocation(string $fingerprint): array
    {
        $query = "select Hostname, CountryCode from {$this->tables->networkStatus} where Fingerprint = ?";
        return $this->db->singleRow($query, [$fingerprint], 1800);
    }

    public function countExitRoutersByIp(string $remoteIp): int
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $orAddresses = $this->tables->orAddresses;
        $params = [];
        $ipPredicate = $this->buildIpPredicate("$networkStatus.IP", "$orAddresses.address", $remoteIp, $params);
        $params[] = 1;

        $query = "select count(distinct $networkStatus.Fingerprint) as Count
            from $networkStatus
                inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint
                left join $orAddresses on $descriptor.ID = $orAddresses.descriptor_id
            where $ipPredicate and $networkStatus.FExit = ?";
        $record = $this->db->singleRow($query, $params, 1800);

        return (int)($record['Count'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchExitPolicyCandidates(string $remoteIp): array
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $orAddresses = $this->tables->orAddresses;
        $params = [];
        $ipPredicate = $this->buildIpPredicate("$networkStatus.IP", "$orAddresses.address", $remoteIp, $params);
        $query = "select $networkStatus.Name Name, $networkStatus.Fingerprint Fingerprint, $descriptor.ExitPolicySERDATA ExitPolicySERDATA
            from $networkStatus
                inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint
                left join $orAddresses on $descriptor.ID = $orAddresses.descriptor_id
            where $ipPredicate
            group by Name, Fingerprint, ExitPolicySERDATA";

        return $this->db->rows($query, $params, 1800);
    }

    /** @return array<string, mixed> */
    public function fetchAggregateStats(): array
    {
        $query = "select
            (select count(*) from {$this->tables->networkStatus}) as 'Total',
            (select count(*) from {$this->tables->networkStatus} where FAuthority = ?) as 'Authority',
            (select count(*) from {$this->tables->networkStatus} where FBadDirectory = ?) as 'BadDirectory',
            (select count(*) from {$this->tables->networkStatus} where FBadExit = ?) as 'BadExit',
            (select count(*) from {$this->tables->networkStatus} where FExit = ?) as 'Exit',
            (select count(*) from {$this->tables->networkStatus} where FFast = ?) as 'Fast',
            (select count(*) from {$this->tables->networkStatus} where FGuard = ?) as 'Guard',
            (select count(*) from {$this->tables->descriptor} inner join {$this->tables->networkStatus} on {$this->tables->networkStatus}.Fingerprint = {$this->tables->descriptor}.Fingerprint where Hibernating = ?) as 'Hibernating',
            (select count(*) from {$this->tables->networkStatus} where FNamed = ?) as 'Named',
            (select count(*) from {$this->tables->networkStatus} where FStable = ?) as 'Stable',
            (select count(*) from {$this->tables->networkStatus} where FRunning = ?) as 'Running',
            (select count(*) from {$this->tables->networkStatus} where FValid = ?) as 'Valid',
            (select count(*) from {$this->tables->networkStatus} where FV2Dir = ?) as 'V2Dir',
            (select count(*) from {$this->tables->networkStatus} where FHSDir = ?) as 'HSDir',
            (select count(*) from {$this->tables->networkStatus} where DirPort > ?) as 'DirMirror'";

        return $this->db->singleRow($query, [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0], 1800);
    }

    public function fetchRouterPage(IndexRequest $request): RouterPage
    {
        $base = $this->buildRouterQuery($request);
        $orderedSql = $this->appendOrderBy($base['sql'], $request);

        $countRecord = $this->db->singleRow(
            "SELECT COUNT(*) AS Count FROM ({$base['sql']}) AS countQuery",
            $base['params'],
            -1,
            'Count query failed'
        );
        $totalResults = (int)($countRecord['Count'] ?? 0);

        $totalPages = max(1, (int)ceil($totalResults / $request->rowsPerPage));
        $page = min($request->page, $totalPages);
        $offset = ($page - 1) * $request->rowsPerPage;

        $query = $orderedSql . ' LIMIT ? OFFSET ?';
        $params = array_merge($base['params'], [$request->rowsPerPage, $offset]);
        $result = $this->db->result($query, $params);

        return new RouterPage($result, $totalResults, $totalPages, $page);
    }

    public function fetchRouterExport(IndexRequest $request): \mysqli_result
    {
        $base = $this->buildRouterQuery($request);
        $query = $this->appendOrderBy($base['sql'], $request);
        return $this->db->result($query, $base['params'], 'Export query failed');
    }

    public function countRoutersByIp(string $ip): int
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $orAddresses = $this->tables->orAddresses;
        $params = [];
        $ipPredicate = $this->buildIpPredicate("$networkStatus.IP", "$orAddresses.address", $ip, $params);
        $query = "select count(distinct $networkStatus.Fingerprint) as Count
            from $networkStatus
                inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint
                left join $orAddresses on $descriptor.ID = $orAddresses.descriptor_id
            where $ipPredicate";
        $record = $this->db->singleRow($query, $params, 1800);

        return (int)($record['Count'] ?? 0);
    }

    /** @return array<int, array{name: string, fingerprint: string, exitPolicy: array<int, string>|null}> */
    public function fetchRoutersByIp(string $ip, bool $includeExitPolicy): array
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $orAddresses = $this->tables->orAddresses;
        $params = [];
        $ipPredicate = $this->buildIpPredicate("$networkStatus.IP", "$orAddresses.address", $ip, $params);
        $selectExitPolicy = $includeExitPolicy ? ", $descriptor.ExitPolicySERDATA" : '';
        $groupExitPolicy = $includeExitPolicy ? ", $descriptor.ExitPolicySERDATA" : '';
        $query = "select $networkStatus.Name, $networkStatus.Fingerprint$selectExitPolicy
            from $networkStatus
                inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint
                left join $orAddresses on $descriptor.ID = $orAddresses.descriptor_id
            where $ipPredicate
            group by $networkStatus.Name, $networkStatus.Fingerprint$groupExitPolicy";

        $result = $this->db->result($query, $params);
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
            ['Total routers', $routerCount],
            ['Current result set', $currentResultSet],
            ['Authority routers', (int)($aggregateStats['Authority'] ?? 0)],
            ['Bad directory routers', (int)($aggregateStats['BadDirectory'] ?? 0)],
            ['Bad exit routers', (int)($aggregateStats['BadExit'] ?? 0)],
            ['Exit routers', (int)($aggregateStats['Exit'] ?? 0)],
            ['Fast routers', (int)($aggregateStats['Fast'] ?? 0)],
            ['Guard routers', (int)($aggregateStats['Guard'] ?? 0)],
            ['Hibernating routers', (int)($aggregateStats['Hibernating'] ?? 0)],
            ['Named routers', (int)($aggregateStats['Named'] ?? 0)],
            ['Stable routers', (int)($aggregateStats['Stable'] ?? 0)],
            ['Running routers', (int)($aggregateStats['Running'] ?? 0)],
            ['Valid routers', (int)($aggregateStats['Valid'] ?? 0)],
            ['V2Dir routers', (int)($aggregateStats['V2Dir'] ?? 0)],
            ['HSDir routers', (int)($aggregateStats['HSDir'] ?? 0)],
            ['Directory mirrors', (int)($aggregateStats['DirMirror'] ?? 0)],
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

    /** @return array{sql: string, params: array<int, mixed>} */
    private function buildRouterQuery(IndexRequest $request): array
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $orAddresses = $this->tables->orAddresses;

        $query = "select $networkStatus.Name, $networkStatus.Fingerprint";
        $query .= ", $networkStatus.CountryCode";
        $query .= ", floor($descriptor.BandwidthOBSERVED / 1024) as Bandwidth";
        $query .= ", floor(((UNIX_TIMESTAMP() - (UNIX_TIMESTAMP($descriptor.LastDescriptorPublished) + ?)) + CAST($descriptor.Uptime AS DECIMAL)) / 3600) as Uptime";
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
        $query .= ", $networkStatus.FFast as 'Fast'";
        $query .= ", $networkStatus.FGuard as 'Guard'";
        $query .= ", $descriptor.Hibernating as 'Hibernating'";
        $query .= ", $networkStatus.FNamed as Named";
        $query .= ", $networkStatus.FStable as Stable";
        $query .= ", $networkStatus.FRunning as Running";
        $query .= ", $networkStatus.FValid as Valid";
        $query .= ", $networkStatus.FV2Dir as V2Dir";
        $query .= ", $networkStatus.FHSDir as HSDir";
        $query .= ", INET_ATON($networkStatus.IP) as NIP from $networkStatus inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint left join $orAddresses on $descriptor.ID = $orAddresses.descriptor_id";

        $params = [$this->offsetFromGmt];
        $where = $this->buildWhereClauses($request, $params);
        if ($where !== []) {
            $query .= ' where ' . implode(' and ', $where);
        }

        $query .= " group by $descriptor.Fingerprint";

        return ['sql' => $query, 'params' => $params];
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, string>
     */
    private function buildWhereClauses(IndexRequest $request, array &$params): array
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
                $where[] = "$column = ?";
                $params[] = (int)$filterValue;
            }
        }

        $searchPredicate = $this->buildSearchPredicate($request, $params);
        if ($searchPredicate !== null) {
            $where[] = $searchPredicate;
        }

        return $where;
    }

    /** @param array<int, mixed> $params */
    private function buildSearchPredicate(IndexRequest $request, array &$params): ?string
    {
        if ($request->customSearchInput === null) {
            return null;
        }

        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $orAddresses = $this->tables->orAddresses;
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

        if ($field === 'IP') {
            return $this->buildIpSearchPredicate($request->customSearchModifier, $value, "$networkStatus.IP", "$orAddresses.address", $params);
        }

        $column = $fieldMap[$field] ?? $fieldMap['Fingerprint'];

        switch ($request->customSearchModifier) {
            case 'Contains':
                $params[] = '%' . $value . '%';
                return "$column like ?";
            case 'LessThan':
                $params[] = $value;
                return "$column < ?";
            case 'GreaterThan':
                $params[] = $value;
                return "$column > ?";
            case 'Equals':
            default:
                $params[] = $value;
                return "$column = ?";
        }
    }


    /** @param array<int, mixed> $params */
    private function buildIpSearchPredicate(string $modifier, string $value, string $networkIpColumn, string $orAddressColumn, array &$params): string
    {
        if ($modifier === 'Contains') {
            $params[] = '%' . $value . '%';
            $params[] = '%' . $value . '%';
            return "($networkIpColumn like ? or $orAddressColumn like ?)";
        }

        $normalized = IpAddress::normalize($value);
        if ($normalized !== null && $modifier === 'Equals') {
            return $this->buildIpPredicate($networkIpColumn, $orAddressColumn, $normalized, $params);
        }

        $params[] = $value;
        return $networkIpColumn . ($modifier === 'LessThan' ? ' < ?' : ($modifier === 'GreaterThan' ? ' > ?' : ' = ?'));
    }

    /** @param array<int, mixed> $params */
    private function buildIpPredicate(string $networkIpColumn, string $orAddressColumn, string $ip, array &$params): string
    {
        $normalized = IpAddress::normalize($ip) ?? $ip;
        $variants = IpAddress::databaseVariants($normalized);
        $placeholders = implode(', ', array_fill(0, count($variants), '?'));

        $params[] = $normalized;
        foreach ($variants as $variant) {
            $params[] = $variant;
        }

        return "($networkIpColumn = ? or $orAddressColumn in ($placeholders))";
    }

    private function appendOrderBy(string $query, IndexRequest $request): string
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $sortRequest = $request->sortRequest;
        $sortOrder = $request->sortOrder === 'Desc' ? 'Desc' : 'Asc';

        $fieldMap = [
            'Name' => 'Name',
            'Fingerprint' => "$networkStatus.Fingerprint",
            'CountryCode' => "$networkStatus.CountryCode",
            'Bandwidth' => 'Bandwidth',
            'Uptime' => 'Uptime',
            'LastDescriptorPublished' => "$networkStatus.LastDescriptorPublished",
            'IP' => 'NIP',
            'Hostname' => "$networkStatus.Hostname",
            'ORPort' => "$networkStatus.ORPort",
            'DirPort' => "$networkStatus.DirPort",
            'Platform' => "$descriptor.Platform",
            'Contact' => "$descriptor.Contact",
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

        $column = $fieldMap[$sortRequest] ?? 'Name';
        $tieBreaker = $sortRequest === 'Name' ? '' : ', Name Asc';
        return $query . " order by $column $sortOrder$tieBreaker";
    }
}
