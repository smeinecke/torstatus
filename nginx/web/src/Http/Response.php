<?php

declare(strict_types=1);

namespace TorStatus\Http;

final class Response
{
    public static function badRequest(): never
    {
        self::sendStatus('400 Bad Request');
        exit;
    }

    public static function serviceUnavailable(string $reason): never
    {
        error_log("HTTP 503 returned to client; reason: $reason");
        self::sendStatus('503 Service Temporarily Unavailable');
        exit;
    }

    private static function sendStatus(string $status): void
    {
        header('HTTP/1.1 ' . $status);
        header('Status: ' . $status);
    }
}
