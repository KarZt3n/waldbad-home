<?php

namespace App\Logic\Membership\ContributionRate\Manager;

use App\Logic\Membership\ContributionRate\ContributionRateSettingsProcessorInterface;
use App\Logic\Membership\ContributionRate\ContributionRateSettingsProviderInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;

readonly class ContributionRateSettingsManager implements ContributionRateSettingsManagerInterface
{
    public function __construct(
        private ContributionRateSettingsProviderInterface $provider,
        private ContributionRateSettingsProcessorInterface $processor,
    ) {
    }

    public function get(): ContributionRateSettings
    {
        return $this->provider->get();
    }

    public function save(ContributionRateSettings $settings): ContributionRateSettings
    {
        return $this->processor->save($settings);
    }
}
