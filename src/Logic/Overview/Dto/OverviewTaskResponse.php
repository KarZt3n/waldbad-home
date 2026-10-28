<?php

namespace App\Logic\Overview\Dto;

use App\Logic\Overview\Model\OverviewTaskType;

readonly class OverviewTaskResponse
{
    public function __construct(
        public OverviewTaskType $type,
        public int $count,
        public ?\DateTimeImmutable $earliestAt,
    ) {
    }
}
