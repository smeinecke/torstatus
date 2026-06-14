<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

@session_start() or die();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/src/Graph/GraphSessionStore.php';
require_once __DIR__ . '/src/Graph/SessionBarGraphRenderer.php';

use TorStatus\Graph\SessionBarGraphRenderer;

(new SessionBarGraphRenderer($_SESSION))->render('SummaryGraph', 1144, 398, [40, 10, 30, 30], false);
