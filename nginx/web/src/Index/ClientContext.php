<?php

declare(strict_types=1);

namespace TorStatus\Index;

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
        $remoteAddr = isset($server['REMOTE_ADDR']) ? (string)$server['REMOTE_ADDR'] : '';
        $forwardedFor = isset($server['HTTP_X_FORWARDED_FOR']) ? (string)$server['HTTP_X_FORWARDED_FOR'] : '';
        $remoteIp = $remoteAddr;

        if ($forwardedFor !== '' && in_array($remoteAddr, $trustedProxies, true)) {
            $xff = array_map('trim', explode(',', $forwardedFor));
            $xff = array_reverse($xff);
            $clientIp = filter_var($xff[0], FILTER_VALIDATE_IP);
            if ($clientIp !== false) {
                $remoteIp = (string)$clientIp;
            }
        }

        $serverIp = $forwardedFor !== '' ? $realServerIp : (string)($server['SERVER_ADDR'] ?? '');
        $serverPort = (string)($server['SERVER_PORT'] ?? '');

        return new self($remoteIp, $serverIp, $serverPort);
    }
}
