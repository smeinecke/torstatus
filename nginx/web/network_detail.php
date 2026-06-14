<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once 'init.php';

use TorStatus\Index\TableNames;
use TorStatus\Network\NetworkDetailService;

$pageTitle = 'Network Detail';

$tables = new TableNames($ActiveNetworkStatusTable, $ActiveDescriptorTable, $ActiveORAddressesTable);
(new NetworkDetailService($db, $tables, (int)$OffsetFromGMT))->prepareGraphs($_SESSION);

render('network_detail.html.twig');

$mysqli->close();
