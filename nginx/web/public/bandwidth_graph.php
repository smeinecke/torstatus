<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once __DIR__ . '/../bootstrap.php';

use TorStatus\Graph\PublicGraphEndpoint;

PublicGraphEndpoint::renderSessionBarGraph('BWGraph', 564, 300, [40, 10, 30, 80], true);
