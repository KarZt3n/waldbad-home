<?php

namespace App\Logic\Membership\Application\Model;

enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
}
