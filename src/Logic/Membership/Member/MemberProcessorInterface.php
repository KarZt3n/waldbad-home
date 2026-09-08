<?php

namespace App\Logic\Membership\Member;

use App\Logic\Membership\Member\Model\Member;

interface MemberProcessorInterface
{
    public function save(Member $member): Member;

    public function delete(string $id): void;
}
