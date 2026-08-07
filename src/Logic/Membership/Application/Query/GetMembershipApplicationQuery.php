<?php

namespace App\Logic\Membership\Application\Query;

use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;

readonly class GetMembershipApplicationQuery
{
    public function __construct(private MembershipApplicationManagerInterface $manager)
    {
    }

    public function execute(string $id): MembershipApplicationResponse
    {
        return MembershipApplicationResponse::fromApplication($this->manager->get($id));
    }
}
