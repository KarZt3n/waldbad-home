<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Model;

readonly class WorkAssignmentRefundObstacle
{
    public function __construct(
        public WorkAssignmentRefundObstacleKind $kind,
        public string $message,
    ) {
    }
}
