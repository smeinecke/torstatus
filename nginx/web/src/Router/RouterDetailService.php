<?php

declare(strict_types=1);

namespace TorStatus\Router;

use TorStatus\Database\QueryExecutor;
use TorStatus\Index\TableNames;

final class RouterDetailService
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

    /** @return array<string, mixed>|null */
    public function findByFingerprint(string $fingerprint): ?array
    {
        $networkStatus = $this->tables->networkStatus;
        $descriptor = $this->tables->descriptor;
        $query = "select
                $networkStatus.Name,
                $descriptor.LastDescriptorPublished,
                $networkStatus.IP,
                $networkStatus.Hostname,
                $networkStatus.ORPort,
                $networkStatus.DirPort,
                $descriptor.Platform,
                $descriptor.Contact,
                CAST(UNIX_TIMESTAMP() AS SIGNED) - CAST(UNIX_TIMESTAMP($descriptor.LastDescriptorPublished) AS SIGNED) + ? + CAST($descriptor.Uptime AS SIGNED) as Uptime,
                $descriptor.BandwidthMAX,
                $descriptor.BandwidthBURST,
                $descriptor.BandwidthOBSERVED,
                $descriptor.OnionKey,
                $descriptor.SigningKey,
                $descriptor.ExitPolicySERDATA,
                $descriptor.FamilySERDATA,
                $networkStatus.CountryCode,
                $descriptor.Hibernating,
                $networkStatus.FAuthority,
                $networkStatus.FBadDirectory,
                $networkStatus.FBadExit,
                $networkStatus.FExit,
                $networkStatus.FFast,
                $networkStatus.FGuard,
                $networkStatus.FNamed,
                $networkStatus.FStable,
                $networkStatus.FRunning,
                $networkStatus.FValid,
                $networkStatus.FV2Dir
            from $networkStatus
                inner join $descriptor on $networkStatus.Fingerprint = $descriptor.Fingerprint
            where $networkStatus.Fingerprint = ?";

        $record = $this->db->singleRow($query, [$this->offsetFromGmt, $fingerprint], 1800);
        if ($record === []) {
            return null;
        }

        return $this->toTemplateContext($fingerprint, $record);
    }

    /** @param array<string, mixed> $record
     *  @return array<string, mixed>
     */
    private function toTemplateContext(string $fingerprint, array $record): array
    {
        $uptime = (int)($record['Uptime'] ?? -1);
        return [
            'Name' => $record['Name'] ?? null,
            'Fingerprint' => $fingerprint,
            'Fingerprint_formatted' => chunk_split(strtoupper($fingerprint), 4, ' '),
            'IP' => $record['IP'] ?? null,
            'Hostname' => $record['Hostname'] ?? null,
            'ORPort' => $record['ORPort'] ?? null,
            'DirPort' => $record['DirPort'] ?? null,
            'Platform' => $record['Platform'] ?? null,
            'Contact' => $record['Contact'] ?? null,
            'LastDescriptorPublished' => $record['LastDescriptorPublished'] ?? null,
            'Uptime' => $uptime,
            'uptime_days' => floor($uptime / 86400),
            'uptime_hours' => floor(($uptime % 86400) / 3600),
            'uptime_minutes' => floor(($uptime % 3600) / 60),
            'uptime_seconds' => $uptime % 60,
            'Bandwidth_MAX' => $record['BandwidthMAX'] ?? null,
            'Bandwidth_BURST' => $record['BandwidthBURST'] ?? null,
            'Bandwidth_OBSERVED' => $record['BandwidthOBSERVED'] ?? null,
            'OnionKey' => $record['OnionKey'] ?? null,
            'SigningKey' => $record['SigningKey'] ?? null,
            'ExitPolicy_DATA_ARRAY' => $this->safeStringArray($record['ExitPolicySERDATA'] ?? null),
            'Family_DATA_ARRAY' => $this->safeStringArray($record['FamilySERDATA'] ?? null),
            'CountryCode' => $record['CountryCode'] ?? null,
            'FAuthority' => $record['FAuthority'] ?? null,
            'FBadDirectory' => $record['FBadDirectory'] ?? null,
            'FBadExit' => $record['FBadExit'] ?? null,
            'FExit' => $record['FExit'] ?? null,
            'FFast' => $record['FFast'] ?? null,
            'FGuard' => $record['FGuard'] ?? null,
            'FHibernating' => $record['Hibernating'] ?? null,
            'FNamed' => $record['FNamed'] ?? null,
            'FStable' => $record['FStable'] ?? null,
            'FRunning' => $record['FRunning'] ?? null,
            'FValid' => $record['FValid'] ?? null,
            'FV2Dir' => $record['FV2Dir'] ?? null,
        ];
    }

    /** @return array<int, string> */
    private function safeStringArray($serialized): array
    {
        $items = unserialize((string)$serialized, ['allowed_classes' => false]);
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_string'));
    }
}
