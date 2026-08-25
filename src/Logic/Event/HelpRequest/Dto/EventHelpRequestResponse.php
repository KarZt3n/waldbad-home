<?php

namespace App\Logic\Event\HelpRequest\Dto;

use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;
use App\Logic\Event\HelpRequest\Model\SelectedEventActivity;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;

readonly class EventHelpRequestResponse
{
    public function __construct(
        public string $id,
        public string $eventIdentifier,
        public string $eventTitle,
        public string $eventDate,
        public string $eventTime,
        public string $firstName,
        public string $lastName,
        public string $message,
        public EventHelpRequestStatus $status,
        public ?int $participationMinutes,
        /** @var list<ParticipationInterval> */
        public array $participationIntervals,
        /** @var list<SelectedEventActivity> */
        public array $selectedActivities,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromRequest(EventHelpRequest $request, ?VolunteerEvent $currentEvent = null): self
    {
        return new self(
            id: $request->id,
            eventIdentifier: $request->eventIdentifier,
            eventTitle: $currentEvent === null ? $request->eventTitle : $currentEvent->title,
            eventDate: $currentEvent === null ? $request->eventDate : $currentEvent->date,
            eventTime: $currentEvent === null ? $request->eventTime : $currentEvent->time,
            firstName: $request->firstName,
            lastName: $request->lastName,
            message: $request->message,
            status: $request->status,
            participationMinutes: $request->participationMinutes,
            participationIntervals: $request->participationIntervals,
            selectedActivities: $request->selectedActivities,
            submittedAt: $request->submittedAt,
            updatedAt: $request->updatedAt,
        );
    }
}
