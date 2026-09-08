<?php

namespace App\Logic\Membership\ContributionRate;

use App\Logic\Membership\ContributionRate\Model\ContributionRate;

interface ContributionRateProcessorInterface
{
    public function save(ContributionRate $rate): ContributionRate;

    public function delete(string $id): void;
}
