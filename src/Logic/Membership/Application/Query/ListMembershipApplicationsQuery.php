<?php

namespace App\Logic\Membership\Application\Query;

use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;

readonly class ListMembershipApplicationsQuery
{
    public function __construct(private MembershipApplicationManagerInterface $manager)
    {
    }

    /**
     * @return list<MembershipApplicationResponse>
     */
    public function execute(): array
    {
        return array_map(MembershipApplicationResponse::fromApplication(...), $this->manager->list());
    }
}
