<?php

declare(strict_types=1);

namespace TorStatus;

use TorStatus\Database\QueryExecutor;
use TorStatus\Http\Response;
use TorStatus\Template\Renderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class Common
{
    public static function startSession(): void
    {
        @session_start() or Response::badRequest();
    }

    public static function memcached(string $host): \Memcached
    {
        $memcached = new \Memcached();
        $memcached->addServer($host, 11211);
        return $memcached;
    }

    public static function database(string $server, string $user, string $password, string $catalog): \mysqli
    {
        mysqli_report(MYSQLI_REPORT_STRICT);
        try {
            $mysqli = new \mysqli($server, $user, $password, $catalog);
            if ($mysqli->connect_error) {
                Response::serviceUnavailable('Could not connect to: ' . $mysqli->connect_error);
            }
        } catch (\Throwable $e) {
            Response::serviceUnavailable('Could not connect to: ' . $e->getMessage());
        }
        mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT ^ MYSQLI_REPORT_INDEX);

        return $mysqli;
    }

    /** @return array<string, mixed> */
    public static function status(QueryExecutor $db): array
    {
        return $db->singleRow(
            'select LastUpdate, LastUpdateElapsed, ActiveNetworkStatusTable, ActiveDescriptorTable, ActiveORAddressesTable from Status',
            [],
            60
        );
    }

    public static function fetchMirrors(QueryExecutor $db): string
    {
        $row = $db->singleRow('SELECT mirrors FROM `Mirrors` WHERE id = ?', [1], 86400);
        return (string)($row['mirrors'] ?? '');
    }

    public static function appVersion(string $composerJsonPath): string
    {
        $composerJson = json_decode((string)file_get_contents($composerJsonPath), true);
        return is_array($composerJson) && isset($composerJson['version']) ? (string)$composerJson['version'] : '4.0';
    }

    /** @param array<string, mixed> $defaultContext */
    public static function renderer(string $templateDirectory, array $defaultContext): Renderer
    {
        $loader = new FilesystemLoader($templateDirectory);
        $twig = new Environment($loader, [
            'cache' => false,
            'debug' => false,
            'autoescape' => 'html',
        ]);

        return new Renderer($twig, $defaultContext);
    }

    public static function isOnionHost(string $host): bool
    {
        return preg_match('/^[0-9a-z]+\.onion$/', $host) === 1;
    }
}
