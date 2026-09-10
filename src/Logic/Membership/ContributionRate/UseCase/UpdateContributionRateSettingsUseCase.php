<?php

namespace App\Logic\Membership\ContributionRate\UseCase;

use App\Logic\Membership\ContributionRate\Dto\ContributionRateSettingsResponse;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateSettingsManagerInterface;

/**
 * Setzt das gemeinsame „gültig ab" für alle Beitragssätze von Hand — wird außerdem automatisch
 * vorgerückt, sobald eine geplante Betragsänderung greift (siehe `ContributionRateManager`).
 */
readonly class UpdateContributionRateSettingsUseCase
{
    public function __construct(private ContributionRateSettingsManagerInterface $manager)
    {
    }

    public function execute(?\DateTimeImmutable $validFrom): ContributionRateSettingsResponse
    {
        $settings = $this->manager->get()->withValidFrom($validFrom);

        return ContributionRateSettingsResponse::fromSettings($this->manager->save($settings));
    }
}
