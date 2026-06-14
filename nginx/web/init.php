<?php

require_once __DIR__ . '/../vendor/autoload.php';
// Include configuration settings
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/common.php';

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

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$composerJson = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
$appVersion = $composerJson['version'] ?? '4.0';

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader, [
    'cache' => false,
    'debug' => false,
    'autoescape' => 'html',
]);

function render(string $template, array $context = []): void {
    global $twig, $pageTitle, $noindex, $footerText, $onion_service, $Hidden_Service_URL, $CSInput, $Self, $appVersion;
    $default = [
        'pageTitle' => $pageTitle ?? '',
        'noindex' => $noindex ?? false,
        'footerText' => $footerText ?? '',
        'onion_service' => $onion_service ?? false,
        'Hidden_Service_URL' => $Hidden_Service_URL ?? null,
        'CSInput' => $CSInput ?? null,
        'Self' => $Self ?? $_SERVER['PHP_SELF'] ?? '',
        'version' => $appVersion ?? '4.0',
        'WHOISPath' => defined('WHOISPath') ? WHOISPath : null,
    ];
    $context = array_merge($default, $context);
    echo $twig->render($template, $context);
}
