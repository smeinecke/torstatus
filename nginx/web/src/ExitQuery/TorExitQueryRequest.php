<?php

declare(strict_types=1);

namespace TorStatus\ExitQuery;

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

    /** @param array<string, mixed> $server
     *  @param array<string, mixed> $post
     */
    public static function fromGlobals(array $server, array $post): self
    {
        if (strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return new self(null, null, null);
        }

        return new self(
            self::sanitizeIp($post['QueryIP'] ?? null),
            self::sanitizeIp($post['DestinationIP'] ?? null),
            self::sanitizePort($post['DestinationPort'] ?? null)
        );
    }

    public function hasDestination(): bool
    {
        return $this->destinationIp !== null && $this->destinationPort !== null;
    }

    private static function sanitizeIp($value): ?string
    {
        if (!is_string($value) || strlen($value) > 15) {
            return null;
        }

        $long = ip2long($value);
        if ($long === false || $long === -1) {
            return null;
        }

        return long2ip($long);
    }

    private static function sanitizePort($value): ?string
    {
        if (!is_string($value) || strlen($value) > 5 || !is_numeric($value)) {
            return null;
        }

        $port = (int)$value;
        if ($port < 0 || $port > 65535) {
            return null;
        }

        return (string)$port;
    }
}
