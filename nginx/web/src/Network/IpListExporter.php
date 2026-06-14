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
        $where = $exitOnly ? ' where FExit = ?' : '';
        $params = $exitOnly ? [1] : [];
        $query = "select IP, INET_ATON(IP) as NIP from {$this->tables->networkStatus}{$where} order by NIP Asc";
        $rows = $this->db->rows($query, $params, 1800);

        $output = '';
        foreach ($rows as $record) {
            $output .= (string)$record['IP'] . "\n";
        }

        return $output;
    }
}
