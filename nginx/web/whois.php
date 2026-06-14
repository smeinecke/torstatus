<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'TorStatus\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use TorStatus\Network\IpAddress;

$ip = isset($_GET['ip']) && is_string($_GET['ip']) ? IpAddress::normalize($_GET['ip']) : null;
if ($ip === null) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

header('Location: https://lookup.icann.org/whois/en?q=' . rawurlencode($ip), true, 302);
exit;
