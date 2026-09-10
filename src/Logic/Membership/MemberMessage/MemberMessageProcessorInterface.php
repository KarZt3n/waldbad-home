<?php

namespace App\Logic\Membership\MemberMessage;

use App\Logic\Membership\MemberMessage\Model\MemberMessage;

interface MemberMessageProcessorInterface
{
    public function save(MemberMessage $message): MemberMessage;
}
