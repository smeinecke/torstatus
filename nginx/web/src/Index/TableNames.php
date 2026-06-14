<?php

declare(strict_types=1);

namespace TorStatus\Index;

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
        $this->networkStatus = $networkStatus;
        $this->descriptor = $descriptor;
        $this->orAddresses = $orAddresses;
    }
}
