<?php

declare(strict_types=1);

namespace TorStatus\Graph;

use TorStatus\Database\QueryExecutor;
use TorStatus\Database\SqlIdentifier;
use TorStatus\Http\Response;

final class BandwidthHistoryGraphRenderer
{
    /** @param array<string, mixed> $get */
    public function renderJson(QueryExecutor $db, string $descriptorTable, array $get): void
    {
        $mode = $this->mode($get['MODE'] ?? null);
        $fingerprint = $this->fingerprint($get['FP'] ?? null);
        if ($mode === null || $fingerprint === null) {
            Response::badRequest();
        }

        $history = $this->fetchHistory($db, $descriptorTable, $fingerprint);
        $series = $mode === 'WriteHistory' ? $history['write'] : $history['read'];
        $title = $mode === 'WriteHistory'
            ? 'Recent Write History (Bytes/Sec Average) (GMT)'
            : 'Recent Read History (Bytes/Sec Average) (GMT)';

        $pointCount = count($series['data']);
        $end = strtotime($series['last']);
        if ($end === false) {
            $end = time();
        }
        $end += $series['increment'];
        $start = $end - ($pointCount * $series['increment']);

        $labels = [];
        $data = [];
        foreach ($series['data'] as $i => $sample) {
            $data[$i] = (int)((float)$sample / $series['increment']);
            $labels[$i] = gmdate('c', $start + ($i * $series['increment']));
        }

        header('Content-Type: application/json');
        echo json_encode([
            'labels' => $labels,
            'data' => $data,
            'title' => $title,
        ]);
    }

    private function mode($value): ?string
    {
        return in_array($value, ['WriteHistory', 'ReadHistory'], true) ? (string)$value : null;
    }

    private function fingerprint($value): ?string
    {
        if (!is_string($value) || !preg_match('/^[a-fA-F0-9]{40}$/', $value)) {
            return null;
        }

        return $value;
    }

    /** @return array{write: array{data: array<int, int|float>, increment: int, last: string}, read: array{data: array<int, int|float>, increment: int, last: string}} */
    private function fetchHistory(QueryExecutor $db, string $descriptorTable, string $fingerprint): array
    {
        $descriptorTable = SqlIdentifier::table($descriptorTable);
        $query = "select WriteHistoryLAST, WriteHistoryINC, WriteHistorySERDATA, ReadHistoryLAST, ReadHistoryINC, ReadHistorySERDATA from $descriptorTable where Fingerprint = ?";
        $record = $db->singleRow($query, [$fingerprint], 1800);
        if ($record === []) {
            $record = null;
        }

        return [
            'write' => $this->series($record, 'WriteHistory'),
            'read' => $this->series($record, 'ReadHistory'),
        ];
    }

    /** @param array<string, mixed>|null $record
     *  @return array{data: array<int, int|float>, increment: int, last: string}
     */
    private function series(?array $record, string $prefix): array
    {
        $data = [];
        $increment = 10;
        $last = gmdate('Y-m-d H:i:s');

        if ($record !== null) {
            $unserialized = unserialize((string)($record[$prefix . 'SERDATA'] ?? ''), ['allowed_classes' => false]);
            if (is_array($unserialized)) {
                $data = array_values(array_filter($unserialized, 'is_numeric'));
            }
            $increment = max(10, (int)($record[$prefix . 'INC'] ?? 10));
            $last = (string)($record[$prefix . 'LAST'] ?? $last);
        }

        if (count($data) < 2) {
            $data = [0, 0];
        }

        return ['data' => $data, 'increment' => $increment, 'last' => $last];
    }
}
