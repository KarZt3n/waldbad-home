<?php

namespace App\UI\Event\HelpRequest\Http;

use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;

readonly class EventHelpRequestResponseFactory
{
    /**
     * @param list<EventHelpRequestResponse> $requests
     * @return array{items: list<array<string, mixed>>}
     */
    public function collection(array $requests): array
    {
        return ['items' => array_map($this->request(...), $requests)];
    }

    /**
     * @return array<string, mixed>
     */
    public function request(EventHelpRequestResponse $request): array
    {
        return [
            'id' => $request->id,
            'eventIdentifier' => $request->eventIdentifier,
            'eventTitle' => $request->eventTitle,
            'eventDate' => $request->eventDate,
            'eventTime' => $request->eventTime,
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'message' => $request->message,
            'status' => $request->status->value,
            'participationMinutes' => $request->participationMinutes,
            'participationIntervals' => array_map(
                static fn (ParticipationInterval $interval): array => [
                    'id' => $interval->id,
                    'position' => $interval->position,
                    'fromTime' => $interval->fromTime,
                    'toTime' => $interval->toTime,
                    'minutes' => $interval->minutes,
                ],
                $request->participationIntervals,
            ),
            'submittedAt' => $request->submittedAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $request->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
