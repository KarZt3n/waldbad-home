<?php

namespace App\Logic\Membership\MemberMessage\Manager;

use App\Logic\Membership\MemberMessage\Model\MemberMessage;

interface MemberMessageManagerInterface
{
    public function get(string $id): MemberMessage;

    /**
     * @return list<MemberMessage>
     */
    public function all(): array;

    public function save(MemberMessage $message): MemberMessage;
}
