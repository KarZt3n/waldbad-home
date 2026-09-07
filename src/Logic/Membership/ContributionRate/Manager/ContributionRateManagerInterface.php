<?php

namespace App\Logic\Membership\ContributionRate\Manager;

use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;

interface ContributionRateManagerInterface
{
    public function get(string $id): ContributionRate;

    public function findByCategory(ContributionCategory $category): ?ContributionRate;

    /**
     * @return list<ContributionRate>
     */
    public function list(): array;

    public function save(ContributionRate $rate): ContributionRate;

    public function delete(string $id): void;
}
