<?php

namespace App\Logic\Event\Schedule\Manager;

use App\Logic\Event\Schedule\Model\EventSchedule;

interface EventScheduleManagerInterface
{
    public function get(string $id): EventSchedule;

    /** @return list<EventSchedule> */
    public function all(): array;

    public function save(EventSchedule $schedule): EventSchedule;

    public function delete(string $id): void;
}
