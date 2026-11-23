<?php

namespace App\Logic\Event\HelpRequest\Model;

enum EventHelpRequestStatus: string
{
    case New = 'new';
    /** Vor Ort eingetragen, aber noch nicht ausgetragen: genau ein Hilfezeitraum ist noch offen. */
    case Present = 'present';
    case Resolved = 'resolved';
    case Participated = 'participated';
    case NotParticipated = 'not_participated';
}
