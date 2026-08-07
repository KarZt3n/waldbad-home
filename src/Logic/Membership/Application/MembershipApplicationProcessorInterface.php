<?php

namespace App\Logic\Membership\Application;

use App\Logic\Membership\Application\Model\MembershipApplication;

interface MembershipApplicationProcessorInterface
{
    public function save(MembershipApplication $application): MembershipApplication;

    /**
     * @return list<MembershipApplication>
     */
    public function claimPending(int $limit, \DateTimeImmutable $at): array;
}
