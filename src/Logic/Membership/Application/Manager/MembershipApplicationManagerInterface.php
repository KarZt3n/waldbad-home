<?php

namespace App\Logic\Membership\Application\Manager;

use App\Logic\Membership\Application\Model\MembershipApplication;

interface MembershipApplicationManagerInterface
{
    public function get(string $id): MembershipApplication;

    /**
     * @return list<MembershipApplication>
     */
    public function list(): array;

    public function save(MembershipApplication $application): MembershipApplication;
}
