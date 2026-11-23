<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestDuplicateMerger;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestRecipientResolver;
use App\Logic\Event\HelpRequest\Service\ParticipationTimeRounding;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

/**
 * Trägt ein Mitglied nachträglich manuell als Helfer einer Veranstaltung ein — für Fälle, in denen
 * jemand nicht über das öffentliche Formular angemeldet hat (z. B. spontan vor Ort) und die
 * Verwaltung die Anmeldung stattdessen direkt selbst anlegt („Neuen Helfer anmelden" bzw.
 * „Neuen Teilnehmer hinzufügen" im Menü je Veranstaltung, siehe `showEventHelpers` in `assets/admin/events.js`). Anders als
 * `SubmitEventHelpRequestUseCase` gibt es hier keine Aktivitätsauswahl/Belegungsprüfung und keine
 * Bestätigungsmail — die Verwaltung kennt Kontext und Kontaktdaten des Mitglieds bereits.
 *
 * Die Person wird direkt als anwesend markiert, ab der gemäß `ParticipationTimeRounding`
 * abgerundeten aktuellen Uhrzeit (siehe `EventHelpRequest::checkIn()`).
 */
readonly class AddEventHelpRequestUseCase
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private VolunteerEventProviderInterface $eventProvider,
        private MemberManagerInterface $members,
        private EventHelpRequestDuplicateMerger $duplicateMerger,
        private EventHelpRequestRecipientResolver $recipientResolver,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private ParticipationTimeRounding $rounding,
    ) {
    }

    public function execute(string $eventIdentifier, string $memberId): EventHelpRequestResponse
    {
        // `findCurrent` statt `findPublished`, analog zu `ListEventHelpRequestsQuery`: die
        // Veranstaltung soll sich auch dann noch nachtragen lassen, wenn sie inzwischen nicht mehr
        // veröffentlicht ist (z. B. eine kurzfristig noch offene Vergangenheits-Erfassung).
        $event = $this->eventProvider->findCurrent($eventIdentifier)
            ?? throw new BusinessRuleViolationException('Diese Veranstaltung wurde nicht gefunden.');
        // Wirft eine passende Exception, falls die Mitglieds-ID nicht existiert.
        $member = $this->members->get($memberId);

        $existing = $this->duplicateMerger->findExisting($this->manager->all(), $member->id, $eventIdentifier);
        if ($existing !== []) {
            throw new BusinessRuleViolationException('Für dieses Mitglied besteht bereits eine Helferanmeldung zu dieser Veranstaltung.');
        }

        $now = $this->clock->now();
        $request = $this->manager->save(new EventHelpRequest(
            id: $this->identifierGenerator->generate(),
            eventIdentifier: $eventIdentifier,
            eventTitle: $event->title,
            eventDate: $event->date,
            eventTime: $event->time,
            firstName: $member->firstName,
            lastName: $member->lastName,
            message: '',
            status: EventHelpRequestStatus::New,
            participationMinutes: null,
            participationIntervals: [],
            selectedActivities: [],
            submittedAt: $now,
            updatedAt: $now,
            email: $member->email,
            birthDate: $member->birthDate,
            memberId: $member->id,
        ));
        // Getrennt gespeichert, da neue Anmeldungen nur mit Status „Neu" angelegt werden
        // (siehe `DoctrineEventHelpRequestProcessor::save()`).
        $request = $this->manager->save($request->checkIn(
            $this->rounding->checkInTime($now),
            $this->identifierGenerator->generate(),
            $now,
        ));

        return EventHelpRequestResponse::fromRequest(
            $request,
            $event,
            memberNumber: $member->memberNumber,
            memberFirstName: $member->firstName,
            memberLastName: $member->lastName,
            memberStreet: $member->street,
            memberBirthDate: $member->birthDate,
            recipientEmails: $this->recipientResolver->resolve($member, $request->email),
        );
    }
}
