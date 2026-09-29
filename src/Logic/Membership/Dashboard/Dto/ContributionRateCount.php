<?php

namespace App\Logic\Membership\Dashboard\Dto;

readonly class ContributionRateCount
{
    public function __construct(
        public string $label,
        public int $count,
    ) {
    }
}
