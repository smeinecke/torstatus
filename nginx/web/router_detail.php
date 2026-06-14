<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once 'init.php';

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
if ($context === null) {
    http_response_code(404);
    echo 'Unknown fingerprint';
    exit;
}

if (($context['Name'] ?? null) === null) {
    render('router_detail.html.twig', ['error' => 'No Descriptor Available']);
} else {
    render('router_detail.html.twig', $context);
}

$mysqli->close();
