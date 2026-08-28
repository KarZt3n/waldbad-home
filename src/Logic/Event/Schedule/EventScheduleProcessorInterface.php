<?php

namespace App\Logic\Event\Schedule;

use App\Logic\Event\Schedule\Model\EventSchedule;

interface EventScheduleProcessorInterface
{
    public function save(EventSchedule $schedule): EventSchedule;

    public function delete(string $id): void;
}
