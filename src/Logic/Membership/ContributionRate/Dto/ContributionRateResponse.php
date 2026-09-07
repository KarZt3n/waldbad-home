<?php

namespace App\Logic\Membership\ContributionRate\Dto;

use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\PersonGroup;
use App\Logic\Membership\PaymentInterval;

readonly class ContributionRateResponse
{
    public function __construct(
        public string $id,
        public ?ContributionCategory $category,
        public string $label,
        public int $amountCents,
        public PaymentInterval $period,
        public ?PersonGroup $personGroup,
        public ?int $minAge,
        public ?int $maxAge,
        public int $annualAmountCents,
    ) {
    }

    public static function fromRate(ContributionRate $rate): self
    {
        return new self(
            id: $rate->id,
            category: $rate->category,
            label: $rate->label,
            amountCents: $rate->amountCents,
            period: $rate->period,
            personGroup: $rate->personGroup,
            minAge: $rate->minAge,
            maxAge: $rate->maxAge,
            annualAmountCents: $rate->annualAmountCents(),
        );
    }
}
