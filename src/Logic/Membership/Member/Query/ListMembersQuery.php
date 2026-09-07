<?php

namespace App\Logic\Membership\Member\Query;

use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

readonly class ListMembersQuery
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    /**
     * @return list<MemberResponse>
     */
    public function execute(?string $searchTerm = null): array
    {
        return array_map(MemberResponse::fromMember(...), $this->manager->search($searchTerm));
    }
}
