<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

$ip = isset($_GET['ip']) && is_string($_GET['ip']) ? $_GET['ip'] : '';
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

header('Location: https://lookup.icann.org/whois/en?q=' . rawurlencode($ip), true, 302);
exit;
