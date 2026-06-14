<?php

declare(strict_types=1);

namespace TorStatus\Graph;

use TorStatus\Http\Response;

final class PublicGraphEndpoint
{
    /** @param array{0:int,1:int,2:int,3:int} $margin */
    public static function renderSessionBarGraph(string $prefix, int $width, int $height, array $margin, bool $rotateLabels = false): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !@session_start()) {
            Response::badRequest();
        }

        /** @var array<string, mixed> $session */
        $session = $_SESSION;
        (new SessionBarGraphRenderer($session))->render($prefix, $width, $height, $margin, $rotateLabels);
    }
}
