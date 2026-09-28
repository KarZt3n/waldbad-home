<?php

namespace App\Logic\Membership\DirectDebit\Model;

enum DirectDebitPositionKind: string
{
    case Contribution = 'contribution';
    case WorkAssignmentSurcharge = 'work_assignment_surcharge';
    case OneTimeCharge = 'one_time_charge';
}
