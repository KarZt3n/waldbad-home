<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;

/** Detailansicht der Gutschrift eines Haushalts — entspricht der Einzelvorschau einer Lastschrift. */
readonly class WorkAssignmentRefundDetailResponse
{
    /**
     * @param list<WorkAssignmentRefundMemberResponse> $members  alle Haushaltsmitglieder
     * @param list<WorkAssignmentRefundRecord>        $refunds  bisherige Überweisungen im Jahr, neueste zuerst
     */
    public function __construct(
        public int $year,
        public string $defaultExecutionDate,
        public WorkAssignmentRefundOverviewEntryResponse $entry,
        public ?string $bankName,
        public int $creditPerHourCents,
        public int $totalSurchargeCents,
        public DirectDebitCreditor $clubAccount,
        public array $members,
        public array $refunds,
    ) {
    }
}
