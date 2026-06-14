<?php

declare(strict_types=1);

namespace TorStatus\Export;

final class RouterCsvExporter
{
    public const HEADERS = [
        'Fingerprint' => 'Fingerprint',
        'CountryCode' => 'Country Code',
        'Bandwidth' => 'Bandwidth (KB/s)',
        'Uptime' => 'Uptime (Days)',
        'LastDescriptorPublished' => 'Last Descriptor Published (GMT)',
        'Hostname' => 'Hostname',
        'IP' => 'IP Address',
        'ORPort' => 'ORPort',
        'DirPort' => 'DirPort',
        'Platform' => 'Platform',
        'Contact' => 'Contact',
        'Authority' => 'Flag - Authority',
        'BadDir' => 'Flag - Bad Directory',
        'BadExit' => 'Flag - Bad Exit',
        'Exit' => 'Flag - Exit',
        'Fast' => 'Flag - Fast',
        'Guard' => 'Flag - Guard',
        'Hibernating' => 'Flag - Hibernating',
        'Named' => 'Flag - Named',
        'Stable' => 'Flag - Stable',
        'Running' => 'Flag - Running',
        'Valid' => 'Flag - Valid',
        'V2Dir' => 'Flag - V2Dir',
        'HSDir' => 'Flag - HSDir',
    ];

    /** @param array<int, string> $activeColumns */
    public function output(\mysqli_result $result, array $activeColumns): void
    {
        $stream = fopen('php://output', 'wb');
        if ($stream === false) {
            \die_503('Unable to open CSV output stream');
        }

        $this->writeRow($stream, $this->headerRow($activeColumns));
        while ($record = $result->fetch_assoc()) {
            $this->writeRow($stream, $this->dataRow($record, $activeColumns));
        }
        fclose($stream);
    }

    /** @param array<int, string> $activeColumns
     *  @return array<int, string>
     */
    private function headerRow(array $activeColumns): array
    {
        $row = ['Router Name'];
        foreach ($activeColumns as $column) {
            if (isset(self::HEADERS[$column])) {
                $row[] = self::HEADERS[$column];
            }
        }

        return $row;
    }

    /** @param array<string, mixed> $record
     *  @param array<int, string> $activeColumns
     *  @return array<int, string>
     */
    private function dataRow(array $record, array $activeColumns): array
    {
        $row = [$this->csvSafe($record['Name'] ?? '')];
        foreach ($activeColumns as $column) {
            $row[] = $this->csvSafe($this->formatValue($record, $column));
        }

        return $row;
    }

    /** @param array<string, mixed> $record */
    private function formatValue(array $record, string $column): string
    {
        $value = $record[$column] ?? '';
        if ($column === 'Uptime') {
            return (is_numeric($value) && (int)$value > -1) ? (string)$value : 'N/A';
        }
        if ($column === 'DirPort') {
            return (is_numeric($value) && (int)$value > 0) ? (string)$value : 'None';
        }
        if ($column === 'CountryCode') {
            return $value !== '' ? (string)$value : 'N/A';
        }

        return (string)$value;
    }

    private function csvSafe(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /** @param resource $stream
     *  @param array<int, string> $row
     */
    private function writeRow($stream, array $row): void
    {
        fputcsv($stream, $row);
    }
}
