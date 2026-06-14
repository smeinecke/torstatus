<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once __DIR__ . '/../init.php';

use TorStatus\Graph\BandwidthHistoryGraphRenderer;
use TorStatus\Graph\PublicGraphEndpoint;

$type = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '';

$map = [
    'cc' => 'CCGraph',
    'cc_exit' => 'CCExitGraph',
    'uptime' => 'UptimeGraph',
    'bandwidth' => 'BWGraph',
    'platform' => 'PlatformGraph',
    'summary' => 'SummaryGraph',
];

if (isset($map[$type])) {
    PublicGraphEndpoint::renderSessionBarGraphJson($map[$type]);
    exit;
}

if ($type === 'bandwidth_history') {
    (new BandwidthHistoryGraphRenderer())->renderJson($db, $ActiveDescriptorTable, $_GET);
    $mysqli->close();
    exit;
}

http_response_code(400);
echo 'Unknown graph type';
