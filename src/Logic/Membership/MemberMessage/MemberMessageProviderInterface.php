<?php

namespace App\Logic\Membership\MemberMessage;

use App\Logic\Membership\MemberMessage\Model\MemberMessage;

interface MemberMessageProviderInterface
{
    public function find(string $id): ?MemberMessage;

    /**
     * @return list<MemberMessage>
     */
    public function findAll(): array;
}
