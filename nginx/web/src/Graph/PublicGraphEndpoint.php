<?php

declare(strict_types=1);

namespace TorStatus\Graph;

use TorStatus\Http\Response;

final class PublicGraphEndpoint
{
    public static function renderSessionBarGraphJson(string $prefix): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !@session_start()) {
            Response::badRequest();
        }

        /** @var array<string, mixed> $session */
        $session = $_SESSION;
        $graphData = GraphSessionStore::get($session, $prefix);
        header('Content-Type: application/json');
        echo json_encode([
            'labels' => $graphData['labels'],
            'data' => $graphData['data'],
            'title' => $graphData['title'],
            'legend' => $graphData['legend'],
        ]);
    }
}
