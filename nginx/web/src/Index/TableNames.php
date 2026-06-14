<?php

declare(strict_types=1);

namespace TorStatus\Index;

use TorStatus\Database\SqlIdentifier;

final class TableNames
{
    /** @var string */
    public $networkStatus;

    /** @var string */
    public $descriptor;

    /** @var string */
    public $orAddresses;

    public function __construct(string $networkStatus, string $descriptor, string $orAddresses)
    {
        $this->networkStatus = SqlIdentifier::table($networkStatus);
        $this->descriptor = SqlIdentifier::table($descriptor);
        $this->orAddresses = SqlIdentifier::table($orAddresses);
    }
}
