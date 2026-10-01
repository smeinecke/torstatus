<?php

declare(strict_types=1);

namespace TorStatus\Index;

final class RouterPage
{
    public function __construct(
        public readonly \mysqli_result $result,
        public readonly int $totalResults,
        public readonly int $totalPages,
        public readonly int $page,
    ) {
    }
}
