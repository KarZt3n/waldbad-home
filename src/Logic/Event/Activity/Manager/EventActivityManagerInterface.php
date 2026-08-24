<?php

namespace App\Logic\Event\Activity\Manager;

use App\Logic\Event\Activity\Model\EventActivity;

interface EventActivityManagerInterface
{
    public function get(string $id): EventActivity;

    /** @return list<EventActivity> */
    public function all(): array;

    public function save(EventActivity $activity): EventActivity;
}
