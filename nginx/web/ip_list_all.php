<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once 'init.php';

use TorStatus\Index\TableNames;
use TorStatus\Network\IpListExporter;

$tables = new TableNames($ActiveNetworkStatusTable, $ActiveDescriptorTable, $ActiveORAddressesTable);
(new IpListExporter($mysqli, $memcached, $tables))->output(false);

$mysqli->close();
