<?php

namespace App\Logic\Membership\ContributionRate\Dto;

use App\Logic\Membership\ContributionRate\Model\PendingContributionRateChange;
use App\Logic\Membership\ContributionRate\Model\PersonGroup;
use App\Logic\Membership\PaymentInterval;

readonly class UpdateContributionRateRequest
{
    public function __construct(
        public string $id,
        public string $label,
        public int $amountCents,
        public PaymentInterval $period,
        public ?PersonGroup $personGroup,
        public ?int $minAge,
        public ?int $maxAge,
        public ?PendingContributionRateChange $pending,
        public ?\DateTimeImmutable $validFrom = null,
    ) {
    }
}
