<?php

namespace App\Logic\Event\HelpRequest\Dto;

use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;

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
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromRequest(EventHelpRequest $request): self
    {
        return new self(
            id: $request->id,
            eventIdentifier: $request->eventIdentifier,
            eventTitle: $request->eventTitle,
            eventDate: $request->eventDate,
            eventTime: $request->eventTime,
            firstName: $request->firstName,
            lastName: $request->lastName,
            message: $request->message,
            status: $request->status,
            participationMinutes: $request->participationMinutes,
            participationIntervals: $request->participationIntervals,
            submittedAt: $request->submittedAt,
            updatedAt: $request->updatedAt,
        );
    }
}
