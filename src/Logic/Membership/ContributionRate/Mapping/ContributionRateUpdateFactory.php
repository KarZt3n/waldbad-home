<?php

namespace App\Logic\Membership\ContributionRate\Mapping;

use App\Logic\Membership\ContributionRate\Dto\UpdateContributionRateRequest;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\PendingContributionRateChange;

readonly class ContributionRateUpdateFactory
{
    public function fromRequest(ContributionRate $current, UpdateContributionRateRequest $request): ContributionRate
    {
        if ($request->validFrom !== null) {
            return $current->withUpdatedRate(
                $current->label,
                $current->amountCents,
                $current->period,
                $current->personGroup,
                $current->minAge,
                $current->maxAge,
                new PendingContributionRateChange(
                    $request->label,
                    $request->amountCents,
                    $request->period,
                    $request->personGroup,
                    $request->minAge,
                    $request->maxAge,
                    $request->validFrom,
                ),
            );
        }

        return $current->withUpdatedRate(
            $request->label,
            $request->amountCents,
            $request->period,
            $request->personGroup,
            $request->minAge,
            $request->maxAge,
            $request->pending,
        );
    }
}
