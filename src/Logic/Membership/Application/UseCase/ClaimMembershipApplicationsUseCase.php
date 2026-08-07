<?php

namespace App\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;

readonly class ClaimMembershipApplicationsUseCase
{
    public function __construct(
        private MembershipApplicationManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<MembershipApplicationResponse>
     */
    public function execute(int $limit): array
    {
        $normalizedLimit = max(1, min(50, $limit));

        return array_map(
            MembershipApplicationResponse::fromApplication(...),
            $this->manager->claimPending($normalizedLimit, $this->clock->now()),
        );
    }
}
