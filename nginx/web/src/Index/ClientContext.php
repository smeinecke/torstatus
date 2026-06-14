<?php

declare(strict_types=1);

namespace TorStatus\Index;

use TorStatus\Network\IpAddress;

final class ClientContext
{
    /** @var string */
    public $remoteIp;

    /** @var string */
    public $serverIp;

    /** @var string */
    public $serverPort;

    public function __construct(string $remoteIp, string $serverIp, string $serverPort)
    {
        $this->remoteIp = $remoteIp;
        $this->serverIp = $serverIp;
        $this->serverPort = $serverPort;
    }

    /**
     * @param array<string, mixed> $server
     * @param array<int, string> $trustedProxies
     */
    public static function fromServer(array $server, array $trustedProxies, string $realServerIp): self
    {
        $remoteAddr = IpAddress::normalize(isset($server['REMOTE_ADDR']) ? (string)$server['REMOTE_ADDR'] : '') ?? '';
        $forwardedFor = isset($server['HTTP_X_FORWARDED_FOR']) ? (string)$server['HTTP_X_FORWARDED_FOR'] : '';
        $remoteIp = $remoteAddr;

        $trusted = array_filter(array_map(static function (string $proxy): ?string {
            return IpAddress::normalize($proxy);
        }, $trustedProxies));

        if ($forwardedFor !== '' && in_array($remoteAddr, $trusted, true)) {
            $xff = array_reverse(array_map('trim', explode(',', $forwardedFor)));
            $clientIp = IpAddress::normalize(reset($xff));
            if ($clientIp !== null) {
                $remoteIp = $clientIp;
            }
        }

        $serverIp = IpAddress::normalize($forwardedFor !== '' ? $realServerIp : (string)($server['SERVER_ADDR'] ?? '')) ?? '';
        $serverPort = (string)($server['SERVER_PORT'] ?? '');

        return new self($remoteIp, $serverIp, $serverPort);
    }
}
