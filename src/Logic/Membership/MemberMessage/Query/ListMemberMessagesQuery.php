<?php

namespace App\Logic\Membership\MemberMessage\Query;

use App\Logic\Membership\MemberMessage\Dto\MemberMessageResponse;
use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;

readonly class ListMemberMessagesQuery
{
    public function __construct(private MemberMessageManagerInterface $manager)
    {
    }

    /**
     * @return list<MemberMessageResponse>
     */
    public function execute(): array
    {
        return array_map(MemberMessageResponse::fromMessage(...), $this->manager->all());
    }
}
