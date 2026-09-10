<?php

namespace App\Logic\Membership\ContributionRate\UseCase;

use App\Logic\Membership\ContributionRate\Dto\ContributionRateResponse;
use App\Logic\Membership\ContributionRate\Dto\UpdateContributionRateRequest;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Mapping\ContributionRateUpdateFactory;

/**
 * Aktualisiert Bezeichnung, Betrag und Zeitraum eines bestehenden Beitragssatzes. Die Kategorie
 * selbst ist fix und kann hierüber nicht verändert werden, da die automatische Beitragsermittlung
 * (`MemberContributionCalculator`) über sie nachschlägt.
 */
readonly class UpdateContributionRateUseCase
{
    public function __construct(
        private ContributionRateManagerInterface $manager,
        private ContributionRateUpdateFactory $factory,
    )
    {
    }

    public function execute(UpdateContributionRateRequest $request): ContributionRateResponse
    {
        $rate = $this->factory->fromRequest($this->manager->get($request->id), $request);

        return ContributionRateResponse::fromRate($this->manager->save($rate));
    }
}
