<?php

namespace App\Logic\Membership\ContributionRate\UseCase;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\ContributionRate\Dto\ContributionRateResponse;
use App\Logic\Membership\ContributionRate\Dto\CreateContributionRateRequest;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;

readonly class CreateContributionRateUseCase
{
    public function __construct(
        private ContributionRateManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    public function execute(CreateContributionRateRequest $request): ContributionRateResponse
    {
        $rate = new ContributionRate(
            id: $this->identifierGenerator->generate(),
            category: $request->category,
            label: $request->label,
            amountCents: $request->amountCents,
            period: $request->period,
            personGroup: $request->personGroup,
            minAge: $request->minAge,
            maxAge: $request->maxAge,
        );

        return ContributionRateResponse::fromRate($this->manager->save($rate));
    }
}
