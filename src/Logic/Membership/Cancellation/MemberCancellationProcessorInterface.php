<?php

namespace App\Logic\Membership\Cancellation;

use App\Logic\Membership\Cancellation\Model\MemberCancellation;

interface MemberCancellationProcessorInterface
{
    public function save(MemberCancellation $cancellation): MemberCancellation;
}
