<?php

namespace App\Logic\Event\Schedule\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Schedule\Dto\EventScheduleResponse;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Schedule\Model\EventSchedulePeriod;

readonly class ListCurrentYearEventSchedulesQuery
{
    public function __construct(
        private EventScheduleManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<EventScheduleResponse> */
    public function execute(EventScheduleKind $kind, EventSchedulePeriod $period = EventSchedulePeriod::CurrentYear): array
    {
        $currentYear = (int) $this->clock->now()->format('Y');

        $schedules = array_values(array_filter(
            $this->manager->all(),
            static function (EventSchedule $schedule) use ($kind, $period, $currentYear): bool {
                if ($schedule->kind !== $kind || !$schedule->visible) {
                    return false;
                }
                $year = (int) substr($schedule->date, 0, 4);

                return match ($period) {
                    EventSchedulePeriod::CurrentYear => $year === $currentYear,
                    EventSchedulePeriod::FromCurrentYear => $year >= $currentYear,
                };
            },
        ));
        usort($schedules, static function (EventSchedule $left, EventSchedule $right): int {
            $dateComparison = $left->date <=> $right->date;

            return $dateComparison !== 0 ? $dateComparison : $left->time <=> $right->time;
        });

        return array_map(EventScheduleResponse::fromSchedule(...), $schedules);
    }
}
