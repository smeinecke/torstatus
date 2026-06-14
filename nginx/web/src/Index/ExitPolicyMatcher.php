<?php

declare(strict_types=1);

namespace TorStatus\Index;

final class ExitPolicyMatcher
{
    public function wouldAllowExit(array $exitPolicy, string $serverIp, string $serverPort): ?bool
    {
        foreach ($exitPolicy as $exitPolicyLine) {
            if (!is_string($exitPolicyLine) || trim($exitPolicyLine) === '') {
                continue;
            }

            $parts = explode(' ', rtrim($exitPolicyLine), 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$condition, $networkLine] = $parts;
            $matches = [];
            if (!preg_match('/(.*):([^:]*)$/', $networkLine, $matches)) {
                continue;
            }

            $subnet = trim($matches[1], '[]');
            $portExpressions = explode(',', $matches[2]);

            if (!$this->isIpInSubnet($serverIp, $subnet)) {
                continue;
            }

            foreach ($portExpressions as $currentPortExpression) {
                $currentPortExpression = trim($currentPortExpression);
                if ($currentPortExpression === '*') {
                    if ($condition === 'accept' || $condition === 'accept6') {
                        return true;
                    }
                    if ($condition === 'reject' || $condition === 'reject6') {
                        return false;
                    }
                    continue;
                }

                if (strpos($currentPortExpression, '-') !== false) {
                    [$lowerPort, $upperPort] = explode('-', $currentPortExpression, 2);
                    if ((int)$serverPort >= (int)$lowerPort && (int)$serverPort <= (int)$upperPort) {
                        if ($condition === 'accept' || $condition === 'accept6') {
                            return true;
                        }
                        if ($condition === 'reject' || $condition === 'reject6') {
                            return false;
                        }
                    }
                    continue;
                }

                if ($serverPort === $currentPortExpression) {
                    if ($condition === 'accept' || $condition === 'accept6') {
                        return true;
                    }
                    if ($condition === 'reject' || $condition === 'reject6') {
                        return false;
                    }
                }
            }
        }

        return null;
    }

    public function isIpInSubnet(string $ip, string $subnet): bool
    {
        if ($subnet === '*') {
            return true;
        }

        if ($subnet === $ip) {
            return true;
        }

        if (strpos($subnet, '/') === false) {
            return false;
        }

        return \IpUtils::checkIp($ip, $subnet);
    }
}
