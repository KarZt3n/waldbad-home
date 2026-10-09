<?php

namespace App\Logic\Membership\WorkAssignment\Model;

readonly class WorkAssignmentInterval
{
    public function __construct(
        public string $fromTime,
        public string $toTime,
    ) {
    }
}
