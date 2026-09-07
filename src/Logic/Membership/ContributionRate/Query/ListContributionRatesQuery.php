<?php

namespace App\Logic\Membership\ContributionRate\Query;

use App\Logic\Membership\ContributionRate\Dto\ContributionRateResponse;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;

readonly class ListContributionRatesQuery
{
    public function __construct(private ContributionRateManagerInterface $manager)
    {
    }

    /**
     * @return list<ContributionRateResponse>
     */
    public function execute(): array
    {
        return array_map(ContributionRateResponse::fromRate(...), $this->manager->list());
    }
}
