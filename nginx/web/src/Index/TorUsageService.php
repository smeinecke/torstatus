<?php

declare(strict_types=1);

namespace TorStatus\Index;

final class TorUsageService
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
    public function evaluate(string $remoteIp, string $serverIp, string $serverPort): array
    {
        $positiveMatchIp = $this->repository->countExitRoutersByIp($remoteIp) > 0 ? 1 : 0;
        $torNodeName = [];
        $torNodeFingerprint = [];
        $positiveMatchExitPolicy = [];
        $count = 0;

        if ($positiveMatchIp === 1) {
            foreach ($this->repository->fetchExitPolicyCandidates($remoteIp) as $record) {
                $count++;
                $torNodeName[$count] = (string)($record['Name'] ?? '');
                $torNodeFingerprint[$count] = (string)($record['Fingerprint'] ?? '');

                $exitPolicy = @unserialize((string)($record['ExitPolicySERDATA'] ?? ''), ['allowed_classes' => false]);
                if (!is_array($exitPolicy)) {
                    continue;
                }

                $result = $this->exitPolicyMatcher->wouldAllowExit($exitPolicy, $serverIp, $serverPort);
                if ($result !== null) {
                    $positiveMatchExitPolicy[$count] = $result ? 1 : 0;
                }
            }
        }

        return [
            'PositiveMatch_IP' => $positiveMatchIp,
            'Count' => $count,
            'TorNodeName' => $torNodeName,
            'TorNodeFP' => $torNodeFingerprint,
            'PositiveMatch_ExitPolicy' => $positiveMatchExitPolicy,
        ];
    }
}
