<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\ContributionRate\Model\ContributionCategory;

readonly class ContributionOutcome
{
    public function __construct(
        public ?ContributionCategory $category,
        public int $amountCents,
        public ?int $workAssignmentSurchargeCents,
    ) {
    }
}
