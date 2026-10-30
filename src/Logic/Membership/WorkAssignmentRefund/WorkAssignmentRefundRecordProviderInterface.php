<?php

namespace App\Logic\Membership\WorkAssignmentRefund;

use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;

interface WorkAssignmentRefundRecordProviderInterface
{
    /** @return list<WorkAssignmentRefundRecord> neueste zuerst */
    public function findByYear(int $year): array;
}
