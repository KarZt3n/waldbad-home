<?php

namespace App\Logic\Membership\Member\Model;

enum MemberFunction: string
{
    case Board = 'board';
    case Member = 'member';
    case Supporter = 'supporter';
    case Treasurer = 'treasurer';
}
