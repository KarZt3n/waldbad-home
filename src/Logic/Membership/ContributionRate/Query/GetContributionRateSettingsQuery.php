<?php

namespace App\Logic\Membership\ContributionRate\Query;

use App\Logic\Membership\ContributionRate\Dto\ContributionRateSettingsResponse;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateSettingsManagerInterface;

readonly class GetContributionRateSettingsQuery
{
    public function __construct(private ContributionRateSettingsManagerInterface $manager)
    {
    }

    public function execute(): ContributionRateSettingsResponse
    {
        return ContributionRateSettingsResponse::fromSettings($this->manager->get());
    }
}
