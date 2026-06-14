<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once 'init.php';

use TorStatus\Export\RouterCsvExporter;
use TorStatus\Index\IndexRepository;
use TorStatus\Index\IndexRequest;
use TorStatus\Index\TableNames;

$request = IndexRequest::fromGlobals(
    $ColumnList_ACTIVE_DEFAULT,
    $ColumnList_INACTIVE_DEFAULT,
    $_SERVER,
    $_GET,
    $_POST,
    $_SESSION
);

$repository = new IndexRepository(
    $mysqli,
    new TableNames($ActiveNetworkStatusTable, $ActiveDescriptorTable, $ActiveORAddressesTable),
    (int)$OffsetFromGMT
);
$result = $repository->fetchRouterExport($request);

header('Content-Transfer-Encoding: Binary');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: inline; filename=Tor_query_EXPORT.csv');

(new RouterCsvExporter())->output($result, $request->columnListActive);
$result->free();
$mysqli->close();
