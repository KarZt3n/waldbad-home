<?php

namespace App\Logic\Membership\ContributionRate\UseCase;

use App\Logic\Membership\ContributionRate\Dto\ContributionRateResponse;
use App\Logic\Membership\ContributionRate\Dto\UpdateContributionRateRequest;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;

/**
 * Aktualisiert Bezeichnung, Betrag und Zeitraum eines bestehenden Beitragssatzes. Die Kategorie
 * selbst ist fix und kann hierüber nicht verändert werden, da die automatische Beitragsermittlung
 * (`MemberContributionCalculator`) über sie nachschlägt.
 */
readonly class UpdateContributionRateUseCase
{
    public function __construct(private ContributionRateManagerInterface $manager)
    {
    }

    public function execute(UpdateContributionRateRequest $request): ContributionRateResponse
    {
        $rate = $this->manager->get($request->id)->withUpdatedRate(
            $request->label,
            $request->amountCents,
            $request->period,
            $request->personGroup,
            $request->minAge,
            $request->maxAge,
        );

        return ContributionRateResponse::fromRate($this->manager->save($rate));
    }
}
