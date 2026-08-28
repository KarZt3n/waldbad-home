<?php

namespace App\Logic\Event\Schedule;

use App\Logic\Event\Schedule\Model\EventSchedule;

interface EventScheduleProviderInterface
{
    public function find(string $id): ?EventSchedule;

    /** @return list<EventSchedule> */
    public function findAll(): array;
}
