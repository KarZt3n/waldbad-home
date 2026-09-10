<?php

namespace App\Data\Event\HelpRequest\Mapper;

use App\Data\Event\HelpRequest\Entity\EventHelpRequestActivityEntity;
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
                static fn (EventHelpRequestActivityEntity $activity): SelectedEventActivity => new SelectedEventActivity(
                    $activity->getId(), $activity->getActivityId(), $activity->getActivityName(),
                ),
                $entity->getSelectedActivities(),
            ),
            submittedAt: $entity->getSubmittedAt(),
            updatedAt: $entity->getUpdatedAt(),
            isMember: $entity->isMember(),
            email: $entity->getEmail(),
            birthDate: $entity->getBirthDate(),
            memberId: $entity->getMemberId(),
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
            isMember: $request->isMember,
            email: $request->email,
            birthDate: $request->birthDate,
            memberId: $request->memberId,
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
        // Unverändert bleibende Hilfezeiträume/Aktivitäten (per ID bzw. activityId erkannt) behalten
        // dabei bewusst ihre bestehende Entity statt über ein `clear()` samt Neuanlage aller Einträge
        // zu laufen: Ansonsten sind beim Flush kurzzeitig sowohl die alte (noch nicht gelöschte)
        // als auch eine neue Zeile mit denselben fachlichen Daten vorhanden — bei Aktivitäten verletzt
        // das den Unique-Constraint auf (request_id, activity_id), bei Hilfezeiträumen kollidiert die
        // neue Entity in Doctrines Identity-Map mit der alten (dieselbe wiederverwendete ID). Neue
        // Zeiträume/Aktivitäten (z. B. gerade erfasste Teilnahme oder aus `EventHelpRequestDuplicateMerger`
        // zusammengeführte) bekommen ohnehin stets frische IDs (siehe `recordParticipation()`,
        // `EventHelpRequest::mergedWith()`) und werden hier entsprechend neu angelegt.
        $entity->replaceParticipationIntervals($this->syncedIntervalEntities($request, $entity));
        $entity->replaceSelectedActivities($this->syncedActivityEntities($request, $entity));
        $entity->changeMember($request->memberId, $request->updatedAt);
        $entity->changeIdentity($request->firstName, $request->lastName, $request->updatedAt);
    }

    /** @return list<EventHelpRequestActivityEntity> */
    private function activityEntities(EventHelpRequest $request, EventHelpRequestEntity $entity): array
    {
        return array_map(
            static fn (SelectedEventActivity $activity): EventHelpRequestActivityEntity => new EventHelpRequestActivityEntity(
                $activity->id, $entity, $activity->activityId, $activity->activityName,
            ),
            $request->selectedActivities,
        );
    }

    /** @return list<EventHelpRequestActivityEntity> */
    private function syncedActivityEntities(EventHelpRequest $request, EventHelpRequestEntity $entity): array
    {
        $existingByActivityId = [];
        foreach ($entity->getSelectedActivities() as $activityEntity) {
            $existingByActivityId[$activityEntity->getActivityId()] = $activityEntity;
        }

        return array_map(
            static fn (SelectedEventActivity $activity): EventHelpRequestActivityEntity => $existingByActivityId[$activity->activityId]
                ?? new EventHelpRequestActivityEntity($activity->id, $entity, $activity->activityId, $activity->activityName),
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

    /** @return list<EventHelpIntervalEntity> */
    private function syncedIntervalEntities(EventHelpRequest $request, EventHelpRequestEntity $entity): array
    {
        $existingById = [];
        foreach ($entity->getParticipationIntervals() as $intervalEntity) {
            $existingById[$intervalEntity->getId()] = $intervalEntity;
        }

        return array_map(
            static fn (ParticipationInterval $interval): EventHelpIntervalEntity => $existingById[$interval->id]
                ?? new EventHelpIntervalEntity(
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
