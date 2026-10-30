<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Manager;

use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;

interface WorkAssignmentRefundRecordManagerInterface
{
    /** @return list<WorkAssignmentRefundRecord> neueste zuerst */
    public function findByYear(int $year): array;

    public function save(WorkAssignmentRefundRecord $record): WorkAssignmentRefundRecord;
}
