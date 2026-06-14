<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once __DIR__ . '/../init.php';

use TorStatus\Graph\BandwidthHistoryGraphRenderer;

(new BandwidthHistoryGraphRenderer())->render($db, $ActiveDescriptorTable, $_GET);

$mysqli->close();
