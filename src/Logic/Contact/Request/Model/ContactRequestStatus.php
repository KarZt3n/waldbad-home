<?php

namespace App\Logic\Contact\Request\Model;

enum ContactRequestStatus: string
{
    case New = 'new';
    case Resolved = 'resolved';
}
