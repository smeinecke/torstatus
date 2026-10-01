<?php

declare(strict_types=1);

namespace TorStatus\Tests;

use PHPUnit\Framework\TestCase;
use TorStatus\Common;

final class CommonTest extends TestCase
{
    public function testFormatBytesPerSecond(): void
    {
        self::assertSame('500 B/s', Common::formatBytesPerSecond(500));
        self::assertSame('1 KB/s', Common::formatBytesPerSecond(1024));
        self::assertSame('1.5 MB/s', Common::formatBytesPerSecond(1536 * 1024));
        self::assertSame('2 GB/s', Common::formatBytesPerSecond(2 * 1024 ** 3));
        self::assertSame('3 TB/s', Common::formatBytesPerSecond(3 * 1024 ** 4));
    }

    public function testIsOnionHost(): void
    {
        self::assertTrue(Common::isOnionHost('abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd.onion'));
        self::assertTrue(Common::isOnionHost('abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd.onion:8080'));
        self::assertFalse(Common::isOnionHost('example.com'));
        self::assertFalse(Common::isOnionHost('onion.example.com'));
        self::assertFalse(Common::isOnionHost('foo.onion.evil.com'));
    }

    public function testArrayOfStrings(): void
    {
        self::assertSame(['a', 'b'], Common::arrayOfStrings(['a', 'b']));
        self::assertSame([], Common::arrayOfStrings('not-array'));
        self::assertSame([], Common::arrayOfStrings(null));
        self::assertSame(['a'], Common::arrayOfStrings(['a', 5, null, ['x']]));
    }
}
