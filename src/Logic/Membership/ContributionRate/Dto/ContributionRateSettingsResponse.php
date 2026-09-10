<?php

namespace App\Logic\Membership\ContributionRate\Dto;

use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;

readonly class ContributionRateSettingsResponse
{
    public function __construct(
        public ?string $validFrom,
    ) {
    }

    public static function fromSettings(ContributionRateSettings $settings): self
    {
        return new self(validFrom: $settings->validFrom?->format('Y-m-d'));
    }
}
