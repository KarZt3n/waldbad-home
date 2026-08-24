<?php

namespace App\Data\Event\HelpRequest\Mapper;

use App\Data\Event\HelpRequest\Entity\EventHelpRequestEntity;
use App\Data\Event\HelpRequest\Entity\EventHelpIntervalEntity;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;
use App\Logic\Event\HelpRequest\Model\SelectedEventActivity;

readonly class EventHelpRequestMapper
{
    public function toModel(EventHelpRequestEntity $entity): EventHelpRequest
    {
        return new EventHelpRequest(
            id: $entity->getId(),
            eventIdentifier: $entity->getEventIdentifier(),
            eventTitle: $entity->getEventTitle(),
            eventDate: $entity->getEventDate(),
            eventTime: $entity->getEventTime(),
            firstName: $entity->getFirstName(),
            lastName: $entity->getLastName(),
            message: $entity->getMessage(),
            status: EventHelpRequestStatus::from($entity->getStatus()),
            participationMinutes: $entity->getParticipationMinutes(),
            participationIntervals: array_map(
                static fn (EventHelpIntervalEntity $interval): ParticipationInterval => new ParticipationInterval(
                    id: $interval->getId(),
                    position: $interval->getPosition(),
                    fromTime: $interval->getFromTime(),
                    toTime: $interval->getToTime(),
                ),
                $entity->getParticipationIntervals(),
            ),
            selectedActivities: array_map(
                static fn (\App\Data\Event\HelpRequest\Entity\EventHelpRequestActivityEntity $activity): SelectedEventActivity => new SelectedEventActivity(
                    $activity->getId(), $activity->getActivityId(), $activity->getActivityName(),
                ),
                $entity->getSelectedActivities(),
            ),
            submittedAt: $entity->getSubmittedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function createEntity(EventHelpRequest $request): EventHelpRequestEntity
    {
        $entity = new EventHelpRequestEntity(
            id: $request->id,
            eventIdentifier: $request->eventIdentifier,
            eventTitle: $request->eventTitle,
            eventDate: $request->eventDate,
            eventTime: $request->eventTime,
            firstName: $request->firstName,
            lastName: $request->lastName,
            message: $request->message,
            status: $request->status->value,
            participationMinutes: $request->participationMinutes,
            legacyParticipationFromTime: null,
            legacyParticipationToTime: null,
            submittedAt: $request->submittedAt,
            updatedAt: $request->updatedAt,
        );
        $entity->replaceParticipationIntervals($this->intervalEntities($request, $entity));
        $entity->replaceSelectedActivities($this->activityEntities($request, $entity));

        return $entity;
    }

    public function updateEntity(EventHelpRequest $request, EventHelpRequestEntity $entity): void
    {
        $entity->changeParticipation(
            $request->status->value,
            $request->participationMinutes,
            $request->updatedAt,
        );
        $entity->replaceParticipationIntervals($this->intervalEntities($request, $entity));
    }

    /** @return list<\App\Data\Event\HelpRequest\Entity\EventHelpRequestActivityEntity> */
    private function activityEntities(EventHelpRequest $request, EventHelpRequestEntity $entity): array
    {
        return array_map(
            static fn (SelectedEventActivity $activity): \App\Data\Event\HelpRequest\Entity\EventHelpRequestActivityEntity => new \App\Data\Event\HelpRequest\Entity\EventHelpRequestActivityEntity(
                $activity->id, $entity, $activity->activityId, $activity->activityName,
            ),
            $request->selectedActivities,
        );
    }

    /** @return list<EventHelpIntervalEntity> */
    private function intervalEntities(EventHelpRequest $request, EventHelpRequestEntity $entity): array
    {
        return array_map(
            static fn (ParticipationInterval $interval): EventHelpIntervalEntity => new EventHelpIntervalEntity(
                id: $interval->id,
                request: $entity,
                position: $interval->position,
                fromTime: $interval->fromTime,
                toTime: $interval->toTime,
            ),
            $request->participationIntervals,
        );
    }
}
