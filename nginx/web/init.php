<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

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

require_once __DIR__ . '/common.php';

use TorStatus\Common;
use TorStatus\Database\QueryExecutor;

Common::startSession();

$TimeStart = microtime(true);
$memcached = Common::memcached((string)$memcached_host);
$mysqli = Common::database((string)$SQL_Server, (string)$SQL_User, (string)$SQL_Pass, (string)$SQL_Catalog);
$db = new QueryExecutor($mysqli, $memcached);

$status = Common::status($db);
$LastUpdate = (string)($status['LastUpdate'] ?? '');
$LastUpdateElapsed = (string)($status['LastUpdateElapsed'] ?? '');
$ActiveNetworkStatusTable = (string)($status['ActiveNetworkStatusTable'] ?? '');
$ActiveDescriptorTable = (string)($status['ActiveDescriptorTable'] ?? '');
$ActiveORAddressesTable = (string)($status['ActiveORAddressesTable'] ?? '');

$timestamp = time();
$year = date('Y', $timestamp);
$month = date('n', $timestamp);
$day = date('j', $timestamp);
$hour = date('G', $timestamp);
$minute = date('i', $timestamp);
$second = date('s', $timestamp);

$Host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '';
$onion_service = Common::isOnionHost($Host);
$appVersion = Common::appVersion(__DIR__ . '/../composer.json');
$renderer = Common::renderer(__DIR__ . '/templates', [
    'pageTitle' => '',
    'noindex' => false,
    'footerText' => $footerText ?? '',
    'onion_service' => $onion_service,
    'Hidden_Service_URL' => $Hidden_Service_URL ?? null,
    'CSInput' => null,
    'Self' => $_SERVER['SCRIPT_NAME'] ?? '',
    'version' => $appVersion,
    'WHOISPath' => defined('WHOISPath') ? WHOISPath . '?q=' : null,
]);
