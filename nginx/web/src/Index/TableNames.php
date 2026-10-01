<?php

declare(strict_types=1);

namespace TorStatus\Index;

use TorStatus\Database\SqlIdentifier;

final class TableNames
{
    public readonly string $networkStatus;

    public readonly string $descriptor;

    public readonly string $orAddresses;

    public function __construct(string $networkStatus, string $descriptor, string $orAddresses)
    {
        $this->networkStatus = SqlIdentifier::table($networkStatus);
        $this->descriptor = SqlIdentifier::table($descriptor);
        $this->orAddresses = SqlIdentifier::table($orAddresses);
    }
}
