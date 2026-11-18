<?php

namespace App\Logic\Membership\Cancellation\Model;

enum MemberCancellationStatus: string
{
    case Open = 'open';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
