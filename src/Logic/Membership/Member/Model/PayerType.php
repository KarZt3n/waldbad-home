<?php

namespace App\Logic\Membership\Member\Model;

enum PayerType: string
{
    case SelfPayer = 'self_payer';
    case OtherMember = 'other_member';
}
