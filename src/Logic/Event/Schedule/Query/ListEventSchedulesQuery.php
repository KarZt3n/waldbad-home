<?php

namespace App\Logic\Event\Schedule\Query;

use App\Logic\Event\Schedule\Dto\EventScheduleResponse;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;

readonly class ListEventSchedulesQuery
{
    public function __construct(private EventScheduleManagerInterface $manager)
    {
    }

    /** @return list<EventScheduleResponse> */
    public function execute(): array
    {
        $schedules = $this->manager->all();
        usort($schedules, static function (EventSchedule $left, EventSchedule $right): int {
            $dateComparison = $left->date <=> $right->date;

            return $dateComparison !== 0 ? $dateComparison : $left->time <=> $right->time;
        });

        return array_map(EventScheduleResponse::fromSchedule(...), $schedules);
    }
}
