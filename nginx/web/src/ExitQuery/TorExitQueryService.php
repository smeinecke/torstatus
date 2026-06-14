<?php

declare(strict_types=1);

namespace TorStatus\ExitQuery;

use TorStatus\Index\ExitPolicyMatcher;
use TorStatus\Index\IndexRepository;

final class TorExitQueryService
{
    /** @var IndexRepository */
    private $repository;

    /** @var ExitPolicyMatcher */
    private $exitPolicyMatcher;

    public function __construct(IndexRepository $repository, ExitPolicyMatcher $exitPolicyMatcher)
    {
        $this->repository = $repository;
        $this->exitPolicyMatcher = $exitPolicyMatcher;
    }

    /** @return array<string, mixed> */
    public function evaluate(TorExitQueryRequest $request): array
    {
        $isTorServer = false;
        $results = [];

        if ($request->queryIp !== null) {
            $isTorServer = $this->repository->countRoutersByIp($request->queryIp) > 0;
            if ($isTorServer) {
                $routers = $this->repository->fetchRoutersByIp($request->queryIp, $request->hasDestination());
                foreach ($routers as $router) {
                    $exitAllowed = null;
                    if ($request->hasDestination() && $router['exitPolicy'] !== null) {
                        $exitAllowed = $this->exitPolicyMatcher->wouldAllowExit(
                            $router['exitPolicy'],
                            (string)$request->destinationIp,
                            (string)$request->destinationPort
                        );
                    }

                    $results[] = [
                        'name' => $router['name'],
                        'fingerprint' => $router['fingerprint'],
                        'exit_allowed' => $exitAllowed,
                    ];
                }
            }
        }

        return [
            'QueryIP' => $request->queryIp,
            'DestinationIP' => $request->destinationIp,
            'DestinationPort' => $request->destinationPort,
            'has_destination' => $request->hasDestination(),
            'is_tor_server' => $isTorServer,
            'results' => $results,
        ];
    }
}
