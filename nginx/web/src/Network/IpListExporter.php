<?php

declare(strict_types=1);

namespace TorStatus\Network;

use TorStatus\Database\QueryExecutor;
use TorStatus\Index\TableNames;

final class IpListExporter
{
    /** @var QueryExecutor */
    private $db;

    /** @var TableNames */
    private $tables;

    public function __construct(QueryExecutor $db, TableNames $tables)
    {
        $this->db = $db;
        $this->tables = $tables;
    }

    public function output(bool $exitOnly): void
    {
        $filename = $exitOnly ? 'Tor_ip_list_EXIT.csv' : 'Tor_ip_list_ALL.csv';
        $output = $this->buildOutput($exitOnly);

        header('Content-Transfer-Encoding: Binary');
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: inline; filename=$filename");
        echo $output;
    }

    private function buildOutput(bool $exitOnly): string
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $orAddresses = $this->tables->orAddresses;
        $where = $exitOnly ? ' where FExit = ?' : '';
        $params = $exitOnly ? [1] : [];

        $rows = $this->db->rows("select IP from $networkStatus$where", $params, 1800);
        $orRows = $this->db->rows(
            "select $orAddresses.address as IP
                from $orAddresses
                    inner join $descriptor on $descriptor.ID = $orAddresses.descriptor_id
                    inner join $networkStatus on $networkStatus.Fingerprint = $descriptor.Fingerprint$where",
            $params,
            1800
        );

        $ips = [];
        foreach (array_merge($rows, $orRows) as $record) {
            $ip = IpAddress::normalize((string)($record['IP'] ?? ''));
            if ($ip !== null) {
                $ips[$ip] = $ip;
            }
        }

        uasort($ips, static function (string $a, string $b): int {
            return strcmp(IpAddress::sortKey($a), IpAddress::sortKey($b));
        });

        return implode("\n", $ips) . ($ips === [] ? '' : "\n");
    }
}
