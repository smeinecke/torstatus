<?php

declare(strict_types=1);

namespace TorStatus\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TorStatus\Export\RouterCsvExporter;

final class RouterCsvExporterTest extends TestCase
{
    /**
     * Formula-injection guard: values starting with =, +, -, @, tab or CR
     * must be prefixed with a single quote so spreadsheets treat them as text.
     */
    #[DataProvider('dangerousValues')]
    public function testDangerousValuesArePrefixed(string $value): void
    {
        self::assertSame("'".$value, self::csvSafe($value));
    }

    public static function dangerousValues(): array
    {
        return [
            'formula' => ['=1+1'],
            'plus' => ['+cmd'],
            'minus' => ['-2+3'],
            'at' => ['@SUM(1)'],
            'tab' => ["\t=1"],
            'cr' => ["\r=1"],
        ];
    }

    #[DataProvider('safeValues')]
    public function testSafeValuesPassThrough(string $value): void
    {
        self::assertSame($value, self::csvSafe($value));
    }

    public static function safeValues(): array
    {
        return [
            'empty' => [''],
            'hostname' => ['relay.example.org'],
            'number' => ['12345'],
            'text_with_dash' => ['some-thing'],
        ];
    }

    private static function csvSafe(string $value): string
    {
        $exporter = new RouterCsvExporter();
        $method = new \ReflectionMethod($exporter, 'csvSafe');
        return $method->invoke($exporter, $value);
    }
}
