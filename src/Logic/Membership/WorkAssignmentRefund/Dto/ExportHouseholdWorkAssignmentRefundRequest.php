<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

readonly class ExportHouseholdWorkAssignmentRefundRequest
{
    public function __construct(
        public string $payerMemberNumber,
        public int $year,
        public \DateTimeImmutable $executionDate,
    ) {
    }
}
