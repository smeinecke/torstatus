<?php

declare(strict_types=1);

namespace TorStatus\Index;

use TorStatus\Database\QueryExecutor;
use TorStatus\Http\Response;

final class RouterRowBuilder
{
    /** @var QueryExecutor */
    private $db;

    /** @var array<string, string> */
    private $countryCodes;

    /** @var array<int, string> */
    private $notifiedMissingCountries = [];

    /**
     * @param array<string, string> $countryCodes
     */
    public function __construct(QueryExecutor $db, array $countryCodes)
    {
        $this->db = $db;
        $this->countryCodes = $countryCodes;
    }

    /**
     * @param array<int, string> $activeColumns
     * @return array<int, array<string, mixed>>
     */
    public function build(\mysqli_result $result, array $activeColumns): array
    {
        $rows = [];
        while ($record = $result->fetch_assoc()) {
            $countryCode = $this->normalizeCountryCode($record);
            $columns = [];
            foreach ($activeColumns as $columnName) {
                $columns[] = $this->buildColumn($columnName, $record);
            }

            $rows[] = [
                'row_class' => $this->rowClass($record),
                'name_class' => isset($record['Named']) && (int)$record['Named'] === 1 ? 'TRR' : 'TRr',
                'country_code' => $countryCode,
                'country_name' => $this->countryCodes[$countryCode] ?? $this->countryCodes['nna'],
                'Name' => (string)($record['Name'] ?? ''),
                'Fingerprint' => (string)($record['Fingerprint'] ?? ''),
                'columns' => $columns,
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $record */
    private function normalizeCountryCode(array &$record): string
    {
        $countryCode = isset($record['CountryCode']) ? strtolower((string)$record['CountryCode']) : '';
        if ($countryCode !== '' && !isset($this->countryCodes[$countryCode])) {
            $this->notifyOnce('missing_countries', $countryCode, $this->notifiedMissingCountries);
        }

        if ($countryCode === '' || !isset($this->countryCodes[$countryCode])) {
            $record['CountryCode'] = 'NNA';
            return 'nna';
        }

        return $countryCode;
    }

    /** @param array<int, string> $notified */
    private function notifyOnce(string $table, string $countryCode, array &$notified): void
    {
        if (in_array($countryCode, $notified, true)) {
            return;
        }
        if (!in_array($table, ['missing_countries', 'missing_flags'], true)) {
            Response::serviceUnavailable('Invalid notification table');
        }

        $this->db->execute("INSERT INTO $table (country_code) VALUES (?) ON DUPLICATE KEY UPDATE country_code = country_code", [$countryCode]);
        $notified[] = $countryCode;
    }

    /** @param array<string, mixed> $record */
    private function rowClass(array $record): string
    {
        if (isset($record['BadExit']) && (int)$record['BadExit'] === 1) {
            return 'B';
        }
        if (isset($record['Running'], $record['Hibernating']) && (int)$record['Running'] === 0 && (int)$record['Hibernating'] === 0) {
            return 'd';
        }
        if (isset($record['Running'], $record['Hibernating']) && (int)$record['Running'] === 0 && (int)$record['Hibernating'] === 1) {
            return 'R';
        }

        return 'r';
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function buildColumn(string $columnName, array $record): array
    {
        switch ($columnName) {
            case 'Hostname':
                return $this->buildHostnameColumn($record);
            case 'Bandwidth':
                return $this->buildBandwidthColumn($record);
            case 'Fingerprint':
            case 'LastDescriptorPublished':
            case 'Contact':
                return ['type' => 'text', 'value' => (string)($record[$columnName] ?? ''), 'class' => 'TDS'];
            case 'BadDir':
            case 'BadExit':
                return ['type' => 'flag', 'value' => (int)($record[$columnName] ?? 0)];
            case 'Uptime':
                return $this->buildUptimeColumn($record);
            case 'ORPort':
            case 'DirPort':
                return ['type' => 'port', 'value' => (int)($record[$columnName] ?? 0), 'class' => 'TDc'];
            default:
                return ['type' => 'text', 'value' => (string)($record[$columnName] ?? ''), 'class' => 'TDS'];
        }
    }

    /** @param array<string, mixed> $record */
    private function buildHostnameColumn(array $record): array
    {
        $column = [
            'type' => 'hostname',
            'value' => (string)($record['Hostname'] ?? ''),
            'ip' => $record['IP'] ?? null,
            'flags' => $this->statusFlags($record),
        ];

        if (isset($record['Platform'])) {
            $column['platform'] = $this->platformImage((string)$record['Platform']);
            $column['platform_title'] = (string)$record['Platform'];
        }

        return $column;
    }

    /** @param array<string, mixed> $record */
    private function buildBandwidthColumn(array $record): array
    {
        $bandwidth = (float)($record['Bandwidth'] ?? 0);
        if ($bandwidth <= 1000) {
            $background = 'bwr';
            $foreground = '1';
        } elseif ($bandwidth <= 2000) {
            $background = 'bwr1';
            $foreground = '2';
        } elseif ($bandwidth <= 3000) {
            $background = 'bwr2';
            $foreground = '3';
        } elseif ($bandwidth <= 4000) {
            $background = 'bwr3';
            $foreground = '4';
        } elseif ($bandwidth <= 5000) {
            $background = 'bwr4';
            $foreground = '5';
        } elseif ($bandwidth <= 6000) {
            $background = 'bwr5';
            $foreground = '6';
        } elseif ($bandwidth <= 10000) {
            $bandwidth = floor(($bandwidth - 6000) / 4);
            $background = 'bwr6';
            $foreground = '7';
        } else {
            $bandwidth = min(1000, ($bandwidth - 9900) / 90);
            $background = 'bwr7';
            $foreground = '8';
        }

        $bandwidthTop = 1000 / 85;
        if ((int)$bandwidth % 1000 === 0 && $bandwidth != 0) {
            $bandwidth = 999;
        }
        $bar = (int)floor(((int)$bandwidth % 1000) / $bandwidthTop);
        if ($bar > 85) {
            $bar = 85;
        }
        if ($bar === 0) {
            $bar = 1;
        }

        return [
            'type' => 'bandwidth',
            'value' => (int)($record['Bandwidth'] ?? 0),
            'bg' => $background,
            'fg' => $foreground,
            'bar' => $bar,
        ];
    }

    /** @param array<string, mixed> $record */
    private function buildUptimeColumn(array $record): array
    {
        $uptime = (int)($record['Uptime'] ?? -1);
        $class = $uptime >= 5 * 24 ? 'TDcb' : 'TDc';
        $down = (!isset($record['Running']) || (int)$record['Running'] === 0) && (!isset($record['Hibernating']) || (int)$record['Hibernating'] === 0);

        if ($uptime > -1) {
            return [
                'type' => 'uptime',
                'days' => (int)floor($uptime / 24),
                'hours' => $uptime % 24,
                'class' => $class,
                'down' => $down,
            ];
        }

        return ['type' => 'text', 'value' => 'N/A', 'class' => 'TDc', 'down' => true];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<int, string>
     */
    private function statusFlags(array $record): array
    {
        $flags = [];
        $map = [
            'Fast' => 'Fast',
            'Exit' => 'Exit',
            'V2Dir' => 'Dir',
            'HSDir' => 'HSDir',
            'Guard' => 'Guard',
            'Stable' => 'Stable',
            'Authority' => 'Authority',
        ];

        foreach ($map as $recordKey => $flag) {
            if (isset($record[$recordKey]) && (int)$record[$recordKey] === 1) {
                $flags[] = $flag;
            }
        }
        if (isset($record['Valid']) && (int)$record['Valid'] === 0) {
            $flags[] = 'Disputed';
        }

        return $flags;
    }

    private function platformImage(string $platform): string
    {
        $image = 'NotAvailable';
        if (strpos($platform, 'Linux') !== false || strpos($platform, 'linux') !== false) {
            $image = 'Linux';
        }
        if (strpos($platform, 'Windows XP') !== false) {
            $image = 'WindowsXP';
        } elseif (strpos($platform, 'Windows') !== false && strpos($platform, 'server') !== false) {
            $image = 'WindowsServer';
        } elseif (strpos($platform, 'Windows') !== false) {
            $image = 'WindowsOther';
        }
        foreach (['Darwin', 'DragonFly', 'FreeBSD', 'NetBSD', 'Cygwin', 'SunOS', 'OpenBSD'] as $name) {
            if (strpos($platform, $name) !== false) {
                return $name;
            }
        }
        if (strpos($platform, 'IRIX') !== false) {
            return 'IRIX64';
        }

        return $image;
    }
}
