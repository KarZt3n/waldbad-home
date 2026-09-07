<?php

namespace App\Logic\Membership\Member\Query;

use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

readonly class GetMemberQuery
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    public function execute(string $id): MemberResponse
    {
        return MemberResponse::fromMember($this->manager->get($id));
    }
}
