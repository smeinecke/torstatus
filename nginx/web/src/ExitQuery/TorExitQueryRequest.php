<?php

declare(strict_types=1);

namespace TorStatus\ExitQuery;

use TorStatus\Network\IpAddress;

final class TorExitQueryRequest
{
    /** @var string|null */
    public $queryIp;

    /** @var string|null */
    public $destinationIp;

    /** @var string|null */
    public $destinationPort;

    public function __construct(?string $queryIp, ?string $destinationIp, ?string $destinationPort)
    {
        $this->queryIp = $queryIp;
        $this->destinationIp = $destinationIp;
        $this->destinationPort = $destinationPort;
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     */
    public static function fromGlobals(array $server, array $query): self
    {
        if (strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            return new self(null, null, null);
        }

        return new self(
            self::sanitizeIp($query['QueryIP'] ?? null),
            self::sanitizeIp($query['DestinationIP'] ?? null),
            self::sanitizePort($query['DestinationPort'] ?? null)
        );
    }

    public function hasDestination(): bool
    {
        return $this->destinationIp !== null && $this->destinationPort !== null;
    }

    private static function sanitizeIp($value): ?string
    {
        if (!is_string($value) || strlen($value) > 64) {
            return null;
        }

        return IpAddress::normalize($value);
    }

    private static function sanitizePort($value): ?string
    {
        if (!is_string($value) || strlen($value) > 5 || !ctype_digit($value)) {
            return null;
        }

        $port = (int)$value;
        if ($port < 0 || $port > 65535) {
            return null;
        }

        return (string)$port;
    }
}
