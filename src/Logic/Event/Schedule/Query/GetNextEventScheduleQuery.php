<?php

namespace App\Logic\Event\Schedule\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Schedule\Dto\EventScheduleResponse;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleKind;

readonly class GetNextEventScheduleQuery
{
    public function __construct(
        private EventScheduleManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(EventScheduleKind $kind): ?EventScheduleResponse
    {
        $today = $this->clock->now()->format('Y-m-d');

        $upcoming = array_values(array_filter(
            $this->manager->all(),
            static fn (EventSchedule $schedule): bool => $schedule->kind === $kind
                && $schedule->visible
                && $schedule->date >= $today,
        ));
        usort($upcoming, static function (EventSchedule $left, EventSchedule $right): int {
            $dateComparison = $left->date <=> $right->date;

            return $dateComparison !== 0 ? $dateComparison : $left->time <=> $right->time;
        });

        return $upcoming === [] ? null : EventScheduleResponse::fromSchedule($upcoming[0]);
    }
}
