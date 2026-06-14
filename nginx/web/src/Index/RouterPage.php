<?php

declare(strict_types=1);

namespace TorStatus\Index;

final class RouterPage
{
    /** @var \mysqli_result */
    public $result;

    /** @var int */
    public $totalResults;

    /** @var int */
    public $totalPages;

    /** @var int */
    public $page;

    public function __construct(\mysqli_result $result, int $totalResults, int $totalPages, int $page)
    {
        $this->result = $result;
        $this->totalResults = $totalResults;
        $this->totalPages = $totalPages;
        $this->page = $page;
    }
}
