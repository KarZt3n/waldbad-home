<?php

namespace App\Logic\Membership\Cancellation\Query;

use App\Logic\Membership\Cancellation\Dto\MemberCancellationResponse;
use App\Logic\Membership\Cancellation\Manager\MemberCancellationManagerInterface;

readonly class ListMemberCancellationsQuery
{
    public function __construct(private MemberCancellationManagerInterface $manager)
    {
    }

    /**
     * @return list<MemberCancellationResponse>
     */
    public function execute(): array
    {
        return array_map(MemberCancellationResponse::fromCancellation(...), $this->manager->all());
    }
}
