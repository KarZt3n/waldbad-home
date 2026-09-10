<?php

namespace App\Data\Membership\ContributionRate\Mapper;

use App\Data\Membership\ContributionRate\Entity\ContributionRateSettingsEntity;
use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;

readonly class ContributionRateSettingsMapper
{
    public function toModel(ContributionRateSettingsEntity $entity): ContributionRateSettings
    {
        return new ContributionRateSettings(validFrom: $entity->getValidFrom());
    }

    public function createEntity(ContributionRateSettings $settings): ContributionRateSettingsEntity
    {
        return new ContributionRateSettingsEntity(id: ContributionRateSettingsEntity::ID, validFrom: $settings->validFrom);
    }

    public function updateEntity(ContributionRateSettings $settings, ContributionRateSettingsEntity $entity): void
    {
        $entity->update($settings->validFrom);
    }
}
