<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

readonly class ExportHouseholdWorkAssignmentRefundRequest
{
    public function __construct(
        public string $primaryMemberNumber,
        public int $year,
        public \DateTimeImmutable $executionDate,
    ) {
    }
}
