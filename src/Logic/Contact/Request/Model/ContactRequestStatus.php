<?php

namespace App\Logic\Contact\Request\Model;

enum ContactRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
}
