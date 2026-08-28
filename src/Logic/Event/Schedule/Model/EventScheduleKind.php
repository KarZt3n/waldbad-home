<?php

namespace App\Logic\Event\Schedule\Model;

enum EventScheduleKind: string
{
    case Event = 'event';
    case WorkAssignment = 'work_assignment';
}
