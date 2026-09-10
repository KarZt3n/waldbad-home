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
        public bool $isMember,
        public ?string $email,
        public ?\DateTimeImmutable $birthDate,
        public ?string $memberId,
        /**
         * Aktuelle Stammdaten des verknüpften Mitglieds (siehe `ListEventHelpRequestsQuery`), nicht
         * auf der Anmeldung selbst gespeichert — damit sie z. B. nach einer Umnummerierung/Umbenennung
         * stets aktuell sind. `memberFirstName`/`memberLastName` dienen der Verwaltung als Vorlage,
         * um einen Tippfehler in `firstName`/`lastName` der Anmeldung zu korrigieren (siehe
         * „Namen aus Mitglied übernehmen" in `assets/app.js`).
         */
        public ?string $memberNumber = null,
        public ?string $memberFirstName = null,
        public ?string $memberLastName = null,
    ) {
    }

    public static function fromRequest(
        EventHelpRequest $request,
        ?VolunteerEvent $currentEvent = null,
        ?string $memberNumber = null,
        ?string $memberFirstName = null,
        ?string $memberLastName = null,
    ): self {
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
            isMember: $request->isMember,
            email: $request->email,
            birthDate: $request->birthDate,
            memberId: $request->memberId,
            memberNumber: $memberNumber,
            memberFirstName: $memberFirstName,
            memberLastName: $memberLastName,
        );
    }
}
