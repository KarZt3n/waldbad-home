<?php

namespace App\Logic\Membership\Cancellation;

use App\Logic\Membership\Cancellation\Model\MemberCancellation;

interface MemberCancellationProviderInterface
{
    public function find(string $id): ?MemberCancellation;

    /**
     * @return list<MemberCancellation> neueste zuerst
     */
    public function findAll(): array;

    /**
     * @return list<MemberCancellation>
     */
    public function findOpen(): array;
}
