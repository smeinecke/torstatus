<?php

declare(strict_types=1);

namespace TorStatus\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TorStatus\Index\ExitPolicyMatcher;

final class ExitPolicyMatcherTest extends TestCase
{
    private ExitPolicyMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new ExitPolicyMatcher();
    }

    public function testRejectAllBlocksIpv4(): void
    {
        self::assertFalse($this->matcher->wouldAllowExit(['reject *:*'], '203.0.113.5', '443'));
    }

    public function testAcceptRuleAllows(): void
    {
        self::assertTrue($this->matcher->wouldAllowExit(
            ['accept 203.0.113.0/24:443', 'reject *:*'],
            '203.0.113.5',
            '443'
        ));
    }

    public function testFirstMatchingRuleWins(): void
    {
        $policy = ['reject 203.0.113.5:80', 'accept *:*'];
        self::assertFalse($this->matcher->wouldAllowExit($policy, '203.0.113.5', '80'));
        self::assertTrue($this->matcher->wouldAllowExit($policy, '203.0.113.5', '443'));
    }

    public function testNoMatchReturnsNull(): void
    {
        self::assertNull($this->matcher->wouldAllowExit(['accept 203.0.113.0/24:443'], '198.51.100.1', '443'));
        self::assertNull($this->matcher->wouldAllowExit([], '203.0.113.5', '443'));
    }

    public function testPortRangeMatches(): void
    {
        self::assertTrue($this->matcher->wouldAllowExit(['accept *:80-443'], '203.0.113.5', '100'));
        self::assertNull($this->matcher->wouldAllowExit(['accept *:80-443'], '203.0.113.5', '444'));
    }

    public function testCommaSeparatedPorts(): void
    {
        self::assertTrue($this->matcher->wouldAllowExit(['accept *:80,443'], '203.0.113.5', '443'));
        self::assertNull($this->matcher->wouldAllowExit(['accept *:80,443'], '203.0.113.5', '8080'));
    }

    public function testIpv4TargetIgnoresV6Rules(): void
    {
        // A v6-only policy must not accept or reject a v4 target.
        self::assertNull($this->matcher->wouldAllowExit(
            ['accept6 *:*'],
            '203.0.113.5',
            '443'
        ));
        self::assertNull($this->matcher->wouldAllowExit(
            ['reject6 *:*'],
            '203.0.113.5',
            '443'
        ));
    }

    public function testIpv6TargetIgnoresV4Rules(): void
    {
        self::assertNull($this->matcher->wouldAllowExit(
            ['reject *:*'],
            '2001:db8::1',
            '443'
        ));
        self::assertTrue($this->matcher->wouldAllowExit(
            ['reject *:*', 'accept6 *:*'],
            '2001:db8::1',
            '443'
        ));
    }

    public function testIpv6SubnetMatch(): void
    {
        self::assertTrue($this->matcher->wouldAllowExit(
            ['accept6 [2001:db8::]/32:443'],
            '2001:db8::1',
            '443'
        ));
    }

    public function testPrivateKeywordMatchesPrivateIpv4(): void
    {
        self::assertFalse($this->matcher->wouldAllowExit(
            ['reject private:*', 'accept *:*'],
            '192.168.1.1',
            '443'
        ));
        self::assertFalse($this->matcher->wouldAllowExit(
            ['reject private:*', 'accept *:*'],
            '10.0.0.1',
            '443'
        ));
        self::assertTrue($this->matcher->wouldAllowExit(
            ['reject private:*', 'accept *:*'],
            '203.0.113.5',
            '443'
        ));
    }

    #[DataProvider('privateSubnets')]
    public function testPrivateKeywordCoversReservedRanges(string $ip): void
    {
        self::assertFalse($this->matcher->wouldAllowExit(
            ['reject private:*'],
            $ip,
            '443'
        ));
    }

    public static function privateSubnets(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'link_local' => ['169.254.1.1'],
            'rfc1918_a' => ['10.255.0.1'],
            'rfc1918_b' => ['172.16.0.1'],
            'rfc1918_c' => ['192.168.0.1'],
            'unspecified' => ['0.0.0.0'],
        ];
    }

    public function testMalformedLinesAreSkipped(): void
    {
        $policy = ['', 'garbage', 123, 'accept'];
        self::assertNull($this->matcher->wouldAllowExit($policy, '203.0.113.5', '443'));
    }

    public function testExactIpWithoutCidrMatchesOnlyItself(): void
    {
        self::assertTrue($this->matcher->wouldAllowExit(['accept 203.0.113.5:443'], '203.0.113.5', '443'));
        self::assertNull($this->matcher->wouldAllowExit(['accept 203.0.113.5:443'], '203.0.113.6', '443'));
    }

    public function testBracketedIpv6Target(): void
    {
        // Policies may carry bracketed literals: accept6 [2001:db8::1]:443
        self::assertTrue($this->matcher->wouldAllowExit(
            ['accept6 [2001:db8::1]:443'],
            '2001:db8::1',
            '443'
        ));
    }
}
