<?php

namespace App\Logic\Membership\WorkAssignmentRefund;

use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;

interface WorkAssignmentRefundRecordProcessorInterface
{
    public function save(WorkAssignmentRefundRecord $record): WorkAssignmentRefundRecord;
}
