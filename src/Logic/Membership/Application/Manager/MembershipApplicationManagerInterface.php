<?php

namespace App\Logic\Membership\Application\Manager;

use App\Logic\Membership\Application\Model\ApplicationStatus;
use App\Logic\Membership\Application\Model\MembershipApplication;

interface MembershipApplicationManagerInterface
{
    public function get(string $id): MembershipApplication;

    /**
     * @return list<MembershipApplication>
     */
    public function list(?ApplicationStatus $status = null): array;

    public function save(MembershipApplication $application): MembershipApplication;

    /**
     * @return list<MembershipApplication>
     */
    public function claimPending(int $limit, \DateTimeImmutable $at): array;
}
