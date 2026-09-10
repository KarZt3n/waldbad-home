<?php

namespace App\Logic\Membership\ContributionRate\Manager;

use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;

interface ContributionRateSettingsManagerInterface
{
    public function get(): ContributionRateSettings;

    public function save(ContributionRateSettings $settings): ContributionRateSettings;
}
