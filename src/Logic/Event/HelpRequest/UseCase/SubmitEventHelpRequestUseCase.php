<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\Activity\Manager\EventActivityManagerInterface;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\SelectedEventActivity;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

readonly class SubmitEventHelpRequestUseCase
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private VolunteerEventProviderInterface $eventProvider,
        private EventActivityManagerInterface $activityManager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<string> $activityIds */
    public function execute(string $eventIdentifier, string $firstName, string $lastName, string $message, array $activityIds = []): EventHelpRequestResponse
    {
        $event = $this->eventProvider->findPublished($eventIdentifier)
            ?? throw new BusinessRuleViolationException('Für diese Veranstaltung ist keine Helferanmeldung verfügbar.');
        $activityIds = array_values(array_unique(array_map('trim', $activityIds)));
        $allowedIds = array_map(static fn ($assignment): string => $assignment->activityId, $event->activities);
        if ($allowedIds !== [] && $activityIds === []) {
            throw new BusinessRuleViolationException('Bitte wählen Sie mindestens eine Aktivität aus.');
        }
        foreach ($activityIds as $activityId) {
            if (!in_array($activityId, $allowedIds, true)) {
                throw new BusinessRuleViolationException('Die ausgewählte Aktivität gehört nicht zu dieser Veranstaltung.');
            }
        }
        $registrationCounts = [];
        foreach ($this->manager->all() as $existingRequest) {
            if ($existingRequest->eventIdentifier !== $eventIdentifier) {
                continue;
            }
            foreach ($existingRequest->selectedActivities as $selectedActivity) {
                $registrationCounts[$selectedActivity->activityId] = ($registrationCounts[$selectedActivity->activityId] ?? 0) + 1;
            }
        }
        foreach ($event->activities as $assignment) {
            if (in_array($assignment->activityId, $activityIds, true)
                && ($registrationCounts[$assignment->activityId] ?? 0) >= $assignment->requiredHelpers) {
                throw new BusinessRuleViolationException('Eine ausgewählte Aktivität ist bereits vollständig belegt.');
            }
        }

        $now = $this->clock->now();
        $request = new EventHelpRequest(
            id: $this->identifierGenerator->generate(),
            eventIdentifier: $eventIdentifier,
            eventTitle: $event->title,
            eventDate: $event->date,
            eventTime: $event->time,
            firstName: trim($firstName),
            lastName: trim($lastName),
            message: trim($message),
            status: EventHelpRequestStatus::New,
            participationMinutes: null,
            participationIntervals: [],
            selectedActivities: array_map(function (string $activityId): SelectedEventActivity {
                $activity = $this->activityManager->get($activityId);
                if (!$activity->active) {
                    throw new BusinessRuleViolationException('Eine ausgewählte Aktivität ist nicht mehr verfügbar.');
                }

                return new SelectedEventActivity($this->identifierGenerator->generate(), $activity->id, $activity->name);
            }, $activityIds),
            submittedAt: $now,
            updatedAt: $now,
        );

        return EventHelpRequestResponse::fromRequest($this->manager->save($request));
    }
}
