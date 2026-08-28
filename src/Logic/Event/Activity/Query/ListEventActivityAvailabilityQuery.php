<?php

namespace App\Logic\Event\Activity\Query;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\Activity\Dto\EventActivityAvailabilityResponse;
use App\Logic\Event\Activity\Manager\EventActivityManagerInterface;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\SelectedEventActivity;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

readonly class ListEventActivityAvailabilityQuery
{
    public function __construct(
        private VolunteerEventProviderInterface $eventProvider,
        private EventActivityManagerInterface $activityManager,
        private EventHelpRequestManagerInterface $requestManager,
    ) {
    }

    /** @return list<EventActivityAvailabilityResponse> */
    public function execute(string $eventIdentifier): array
    {
        $event = $this->eventProvider->findPublished($eventIdentifier)
            ?? throw new BusinessRuleViolationException('Für diese Veranstaltung ist keine Helferanmeldung verfügbar.');
        $counts = [];
        foreach ($this->requestManager->all() as $request) {
            if ($request->eventIdentifier !== $eventIdentifier) {
                continue;
            }
            foreach ($request->selectedActivities as $selected) {
                $counts[$selected->activityId] = ($counts[$selected->activityId] ?? 0) + 1;
            }
        }

        return array_map(function ($assignment) use ($counts): EventActivityAvailabilityResponse {
            $activity = $this->activityManager->get($assignment->activityId);

            return new EventActivityAvailabilityResponse(
                $activity->id, $activity->name, $activity->description,
                $assignment->requiredHelpers, $counts[$activity->id] ?? 0,
                $assignment->time, $assignment->meetTime, $assignment->meetPlace, $assignment->remark,
            );
        }, $event->activities);
    }
}
