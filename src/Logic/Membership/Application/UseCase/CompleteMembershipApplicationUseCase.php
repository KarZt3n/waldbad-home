<?php

namespace App\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;

readonly class CompleteMembershipApplicationUseCase
{
    public function __construct(
        private MembershipApplicationManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, string $externalReference): MembershipApplicationResponse
    {
        $application = $this->manager->get($id)->complete($externalReference, $this->clock->now());

        return MembershipApplicationResponse::fromApplication($this->manager->save($application));
    }
}
