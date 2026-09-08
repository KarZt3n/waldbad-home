<?php

namespace App\Logic\Membership\Application;

use App\Logic\Membership\Application\Model\MembershipApplication;

interface MembershipApplicationProviderInterface
{
    public function find(string $id): ?MembershipApplication;

    /**
     * @return list<MembershipApplication>
     */
    public function findAll(): array;
}
