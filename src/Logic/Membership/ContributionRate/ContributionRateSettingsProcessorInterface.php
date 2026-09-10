<?php

namespace App\Logic\Membership\ContributionRate;

use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;

interface ContributionRateSettingsProcessorInterface
{
    public function save(ContributionRateSettings $settings): ContributionRateSettings;
}
