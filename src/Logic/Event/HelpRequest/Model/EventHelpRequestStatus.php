<?php

namespace App\Logic\Event\HelpRequest\Model;

enum EventHelpRequestStatus: string
{
    case New = 'new';
    case Resolved = 'resolved';
    case Participated = 'participated';
    case NotParticipated = 'not_participated';
}
