<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once __DIR__ . '/../init.php';

use TorStatus\Index\TableNames;
use TorStatus\Router\RouterDetailService;

$noindex = true;
$pageTitle = 'Router Detail';

$fingerprint = isset($_GET['FP']) && is_string($_GET['FP']) ? strip_tags($_GET['FP']) : null;
if ($fingerprint === null) {
    http_response_code(400);
    echo 'Parameter FP missing';
    exit;
}
if (!preg_match('/^[a-fA-F0-9]{40}$/', $fingerprint)) {
    http_response_code(400);
    echo 'Invalid fingerprint';
    exit;
}

$tables = new TableNames($ActiveNetworkStatusTable, $ActiveDescriptorTable, $ActiveORAddressesTable);
$context = (new RouterDetailService($db, $tables, (int)$OffsetFromGMT))->findByFingerprint($fingerprint);
if ($context !== null) {
    $context['BandwidthWriteGraphUrl'] = 'graphs.php?' . http_build_query(['type' => 'bandwidth_history', 'MODE' => 'WriteHistory', 'FP' => $fingerprint]);
    $context['BandwidthReadGraphUrl'] = 'graphs.php?' . http_build_query(['type' => 'bandwidth_history', 'MODE' => 'ReadHistory', 'FP' => $fingerprint]);
}
if ($context === null) {
    http_response_code(404);
    echo 'Unknown fingerprint';
    exit;
}

if (($context['Name'] ?? null) === null) {
    $renderer->render('router_detail.html.twig', ['pageTitle' => $pageTitle, 'noindex' => $noindex, 'error' => 'No Descriptor Available']);
} else {
    $renderer->render('router_detail.html.twig', array_merge(['pageTitle' => $pageTitle, 'noindex' => $noindex], $context));
}

$mysqli->close();
