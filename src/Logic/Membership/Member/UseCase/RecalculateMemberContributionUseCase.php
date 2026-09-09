<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;

/**
 * Berechnet den Beitrag für ein angefragtes Mitglied und dessen gesamten Haushalt neu (siehe
 * `HouseholdContributionRecalculator`).
 */
readonly class RecalculateMemberContributionUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private HouseholdContributionRecalculator $recalculator,
    ) {
    }

    public function execute(string $memberId): MemberResponse
    {
        $member = $this->manager->get($memberId);

        return MemberResponse::fromMember($this->recalculator->recalculate($member));
    }
}
