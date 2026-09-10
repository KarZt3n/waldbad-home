<?php

namespace App\Logic\Membership\MemberMessage\Model;

enum MemberMessageStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
}
