<?php

declare(strict_types=1);

namespace TorStatus\Index;

use TorStatus\Database\SqlIdentifier;
use TorStatus\Http\Response;

final class TableNames
{
    public readonly string $networkStatus;

    public readonly string $descriptor;

    public readonly string $orAddresses;

    public function __construct(string $networkStatus, string $descriptor, string $orAddresses)
    {
        try {
            $this->networkStatus = SqlIdentifier::table($networkStatus);
            $this->descriptor = SqlIdentifier::table($descriptor);
            $this->orAddresses = SqlIdentifier::table($orAddresses);
        } catch (\InvalidArgumentException $e) {
            Response::serviceUnavailable($e->getMessage());
        }
    }
}
