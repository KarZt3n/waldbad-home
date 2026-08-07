<?php

namespace App\Logic\Membership\Application\Model;

enum MembershipType: string
{
    case Individual = 'individual';
    case Family = 'family';
}
