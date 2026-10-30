<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Manager;

use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;
use App\Logic\Membership\WorkAssignmentRefund\WorkAssignmentRefundRecordProcessorInterface;
use App\Logic\Membership\WorkAssignmentRefund\WorkAssignmentRefundRecordProviderInterface;

readonly class WorkAssignmentRefundRecordManager implements WorkAssignmentRefundRecordManagerInterface
{
    public function __construct(
        private WorkAssignmentRefundRecordProviderInterface $provider,
        private WorkAssignmentRefundRecordProcessorInterface $processor,
    ) {
    }

    public function findByYear(int $year): array
    {
        return $this->provider->findByYear($year);
    }

    public function save(WorkAssignmentRefundRecord $record): WorkAssignmentRefundRecord
    {
        return $this->processor->save($record);
    }
}
