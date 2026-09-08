<?php

namespace App\Logic\Membership\ContributionRate;

use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;

interface ContributionRateProviderInterface
{
    public function find(string $id): ?ContributionRate;

    public function findByCategory(ContributionCategory $category): ?ContributionRate;

    /**
     * @return list<ContributionRate>
     */
    public function findAll(): array;
}
