<?php

declare(strict_types=1);

namespace TorStatus\Index;

use TorStatus\Network\IpAddress;

final class ExitPolicyMatcher
{
    /** @param array<int, mixed> $exitPolicy */
    public function wouldAllowExit(array $exitPolicy, string $serverIp, string $serverPort): ?bool
    {
        $serverIp = IpAddress::normalize($serverIp) ?? $serverIp;

        foreach ($exitPolicy as $exitPolicyLine) {
            if (!is_string($exitPolicyLine) || trim($exitPolicyLine) === '') {
                continue;
            }

            $parts = explode(' ', rtrim($exitPolicyLine), 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$condition, $target] = $parts;
            $target = trim($target);
            $separator = strrpos($target, ':');
            if ($separator === false) {
                continue;
            }

            $subnet = trim(substr($target, 0, $separator), '[]');
            $portExpressions = explode(',', substr($target, $separator + 1));

            if (!$this->isIpInSubnet($serverIp, $subnet)) {
                continue;
            }

            foreach ($portExpressions as $currentPortExpression) {
                if (!$this->portMatches($serverPort, trim($currentPortExpression))) {
                    continue;
                }

                if ($condition === 'accept' || $condition === 'accept6') {
                    return true;
                }
                if ($condition === 'reject' || $condition === 'reject6') {
                    return false;
                }
            }
        }

        return null;
    }

    public function isIpInSubnet(string $ip, string $subnet): bool
    {
        $ip = IpAddress::normalize($ip) ?? $ip;
        $subnet = trim($subnet, '[]');

        if ($subnet === '*') {
            return true;
        }

        if (strpos($subnet, '/') === false) {
            $normalizedSubnet = IpAddress::normalize($subnet);
            return $normalizedSubnet !== null && $normalizedSubnet === $ip;
        }

        return \IpUtils::checkIp($ip, $subnet);
    }

    private function portMatches(string $serverPort, string $expression): bool
    {
        if ($expression === '*') {
            return true;
        }

        if (strpos($expression, '-') !== false) {
            [$lowerPort, $upperPort] = explode('-', $expression, 2);
            return (int)$serverPort >= (int)$lowerPort && (int)$serverPort <= (int)$upperPort;
        }

        return $serverPort === $expression;
    }
}
