<?php

declare(strict_types=1);

namespace TorStatus\Network;

use TorStatus\Index\TableNames;

final class IpListExporter
{
    /** @var \mysqli */
    private $mysqli;

    /** @var \Memcached */
    private $memcached;

    /** @var TableNames */
    private $tables;

    public function __construct(\mysqli $mysqli, \Memcached $memcached, TableNames $tables)
    {
        $this->mysqli = $mysqli;
        $this->memcached = $memcached;
        $this->tables = $tables;
    }

    public function output(bool $exitOnly): void
    {
        $filename = $exitOnly ? 'Tor_ip_list_EXIT.csv' : 'Tor_ip_list_ALL.csv';
        $cacheKey = $exitOnly ? 'torstatus_ip_list_exit_csv' : 'torstatus_ip_list_all_csv';
        $output = $this->cachedOutput($cacheKey, $exitOnly);

        header('Content-Transfer-Encoding: Binary');
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: inline; filename=$filename");
        echo $output;
    }

    private function cachedOutput(string $cacheKey, bool $exitOnly): string
    {
        $cached = $this->memcached->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $where = $exitOnly ? " where FExit = '1'" : '';
        $query = "select IP, INET_ATON(IP) as NIP from {$this->tables->networkStatus}{$where} order by NIP Asc";
        $result = $this->mysqli->query($query);
        if (!$result) {
            \die_503('Query failed: ' . $this->mysqli->error);
        }

        $output = '';
        while ($record = $result->fetch_assoc()) {
            $output .= (string)$record['IP'] . "\n";
        }
        $result->free();

        $this->memcached->set($cacheKey, $output, 1800);

        return $output;
    }
}
