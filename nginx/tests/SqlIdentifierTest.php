<?php

declare(strict_types=1);

namespace TorStatus\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TorStatus\Database\SqlIdentifier;

final class SqlIdentifierTest extends TestCase
{
    #[DataProvider('validIdentifiers')]
    public function testValidIdentifiersAreQuoted(string $input, string $expected): void
    {
        self::assertSame($expected, SqlIdentifier::table($input));
    }

    public static function validIdentifiers(): array
    {
        return [
            'plain' => ['NetworkStatus', '`NetworkStatus`'],
            'underscores_digits' => ['t_network_status_2', '`t_network_status_2`'],
            'schema_qualified' => ['torstatus.NetworkStatus', '`torstatus`.`NetworkStatus`'],
            'single_char' => ['a', '`a`'],
        ];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testInvalidIdentifiersThrow(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SqlIdentifier::table($input);
    }

    public static function invalidIdentifiers(): array
    {
        return [
            'semicolon' => ['users; DROP TABLE'],
            'quote' => ["users'"],
            'double_quote' => ['users"'],
            'backtick_escape' => ['a`b'],
            'space' => ['Network Status'],
            'paren' => ['f()'],
            'star' => ['*'],
            'double_dot' => ['a..b'],
            'leading_dot' => ['.a'],
            'trailing_dot' => ['a.'],
            'empty' => [''],
            'comment' => ['a--b'],
            'unicode' => ['täble'],
        ];
    }

    public function testBacktickInsideIsRejectedNotEscaped(): void
    {
        // `` ` `` cannot occur inside a valid identifier; the escape path only
        // guards against names that already passed the regex.
        $this->expectException(\InvalidArgumentException::class);
        SqlIdentifier::table('a`; DROP TABLE `x');
    }
}
