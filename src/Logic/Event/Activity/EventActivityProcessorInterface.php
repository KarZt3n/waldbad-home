<?php

namespace App\Logic\Event\Activity;

use App\Logic\Event\Activity\Model\EventActivity;

interface EventActivityProcessorInterface
{
    public function save(EventActivity $activity): EventActivity;
}
