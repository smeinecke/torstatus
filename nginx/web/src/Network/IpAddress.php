<?php

declare(strict_types=1);

namespace TorStatus\Network;

final class IpAddress
{
    public static function normalize(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        $ip = trim($ip);
        if ($ip === '') {
            return null;
        }

        if ($ip[0] === '[' && substr($ip, -1) === ']') {
            $ip = substr($ip, 1, -1);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return $ip;
        }

        $normalized = @inet_ntop($packed);
        return is_string($normalized) ? $normalized : $ip;
    }

    public static function isIpv6(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /** @return array<int, string> */
    public static function databaseVariants(string $ip): array
    {
        $normalized = self::normalize($ip) ?? $ip;
        $variants = [$normalized];

        if (self::isIpv6($normalized)) {
            $variants[] = '[' . $normalized . ']';
        }

        return array_values(array_unique($variants));
    }

    public static function sortKey(string $ip): string
    {
        $normalized = self::normalize($ip);
        if ($normalized === null) {
            return '2:' . $ip;
        }

        $packed = @inet_pton($normalized);
        if ($packed === false) {
            return '2:' . $normalized;
        }

        $family = self::isIpv6($normalized) ? '1:' : '0:';
        return $family . bin2hex($packed);
    }
}
