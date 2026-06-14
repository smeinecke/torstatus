<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once __DIR__ . '/../bootstrap.php';

use TorStatus\Network\IpAddress;

$ip = isset($_GET['ip']) && is_string($_GET['ip']) ? IpAddress::normalize($_GET['ip']) : null;
if ($ip === null) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

header('Location: https://https://client.rdap.org/?object=' . rawurlencode($ip), true, 302);
exit;
