<?php

namespace App\Logic\Guestbook\Entry\Model;

enum GuestbookStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Spam = 'spam';
}
