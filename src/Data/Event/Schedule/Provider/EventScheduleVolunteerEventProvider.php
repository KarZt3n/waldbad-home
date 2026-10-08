<?php

namespace App\Data\Event\Schedule\Provider;

use App\Logic\Event\Schedule\EventScheduleProviderInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleActivity;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\Model\VolunteerEventActivity;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

/**
 * Resolves volunteer sign-up events backed by the standalone „Veranstaltung“ module
 * (`EventSchedule`), using the schedule's own id as the stable `eventIdentifier`.
 */
readonly class EventScheduleVolunteerEventProvider implements VolunteerEventProviderInterface
{
    public function __construct(private EventScheduleProviderInterface $provider)
    {
    }

    public function findPublished(string $eventIdentifier): ?VolunteerEvent
    {
        $schedule = $this->provider->find($eventIdentifier);
        if ($schedule === null || !$schedule->visible || !$schedule->helpEnabled) {
            return null;
        }

        return $this->toVolunteerEvent($schedule);
    }

    public function findCurrent(string $eventIdentifier): ?VolunteerEvent
    {
        $schedule = $this->provider->find($eventIdentifier);

        return $schedule === null ? null : $this->toVolunteerEvent($schedule);
    }

    private function toVolunteerEvent(EventSchedule $schedule): VolunteerEvent
    {
        return new VolunteerEvent(
            $schedule->id,
            $schedule->title,
            $schedule->date,
            $schedule->time,
            array_map(
                static fn (EventScheduleActivity $activity): VolunteerEventActivity => new VolunteerEventActivity(
                    activityId: $activity->activityId,
                    requiredHelpers: $activity->requiredHelpers,
                    selectable: $activity->selectable,
                    time: $activity->time,
                    meetTime: $activity->meetTime,
                    meetPlace: $activity->meetPlace,
                    remark: $activity->remark,
                ),
                $schedule->activities,
            ),
        );
    }
}
