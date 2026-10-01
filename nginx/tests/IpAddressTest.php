<?php

declare(strict_types=1);

namespace TorStatus\Tests;

use PHPUnit\Framework\TestCase;
use TorStatus\Network\IpAddress;

final class IpAddressTest extends TestCase
{
    public function testNormalizeIpv4(): void
    {
        self::assertSame('203.0.113.5', IpAddress::normalize('203.0.113.5'));
        self::assertSame('203.0.113.5', IpAddress::normalize('  203.0.113.5 '));
    }

    public function testNormalizeIpv6Compresses(): void
    {
        self::assertSame('2001:db8::1', IpAddress::normalize('2001:0db8:0000:0000:0000:0000:0000:0001'));
    }

    public function testNormalizeStripsBrackets(): void
    {
        self::assertSame('2001:db8::1', IpAddress::normalize('[2001:db8::1]'));
    }

    public function testNormalizeRejectsGarbage(): void
    {
        self::assertNull(IpAddress::normalize('not-an-ip'));
        self::assertNull(IpAddress::normalize(''));
        self::assertNull(IpAddress::normalize('999.999.999.999'));
        self::assertNull(IpAddress::normalize(null));
        self::assertNull(IpAddress::normalize("1.2.3.4; DROP TABLE x"));
    }

    public function testIsIpv6(): void
    {
        self::assertTrue(IpAddress::isIpv6('2001:db8::1'));
        self::assertFalse(IpAddress::isIpv6('203.0.113.5'));
        self::assertFalse(IpAddress::isIpv6('garbage'));
    }

    public function testDatabaseVariants(): void
    {
        self::assertSame(['203.0.113.5'], IpAddress::databaseVariants('203.0.113.5'));

        $v6 = IpAddress::databaseVariants('2001:0db8::1');
        self::assertSame(['2001:db8::1', '[2001:db8::1]'], $v6);
    }

    public function testSortKeyOrdersIpv4BeforeIpv6(): void
    {
        self::assertLessThan(
            IpAddress::sortKey('2001:db8::1'),
            IpAddress::sortKey('203.0.113.5')
        );
        self::assertLessThan(
            IpAddress::sortKey('203.0.113.9'),
            IpAddress::sortKey('203.0.113.5')
        );
    }

    public function testSortKeyFallbackForInvalid(): void
    {
        self::assertSame('2:garbage', IpAddress::sortKey('garbage'));
    }
}
