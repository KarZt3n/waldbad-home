<?php

namespace App\Logic\Media\Library\Model;

enum MediaUsageArea: string
{
    case Page = 'page';
    case EventSchedule = 'event_schedule';
    case EventTemplate = 'event_template';
}
