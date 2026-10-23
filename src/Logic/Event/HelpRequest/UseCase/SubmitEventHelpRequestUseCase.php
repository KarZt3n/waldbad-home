<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\Activity\Manager\EventActivityManagerInterface;
use App\Logic\Event\Activity\Model\EventActivity;
use App\Logic\Event\HelpRequest\Dto\EventHelpCompanionInput;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\EventHelpRequestNotifierInterface;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestSubmission;
use App\Logic\Event\HelpRequest\Model\SelectedEventActivity;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestConfirmationMailer;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestDuplicateMerger;
use App\Logic\Membership\Member\Service\MemberIdentityMatcher;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

readonly class SubmitEventHelpRequestUseCase
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private VolunteerEventProviderInterface $eventProvider,
        private EventActivityManagerInterface $activityManager,
        private MemberIdentityMatcher $memberMatcher,
        private EventHelpRequestDuplicateMerger $duplicateMerger,
        private EventHelpRequestConfirmationMailer $confirmationMailer,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private EventHelpRequestNotifierInterface $notifier,
    ) {
    }

    public const int MAX_COMPANIONS = 8;

    /**
     * @param list<string> $activityIds
     * @param list<EventHelpCompanionInput> $companions
     */
    public function execute(
        string $eventIdentifier,
        string $firstName,
        string $lastName,
        string $message,
        \DateTimeImmutable $birthDate,
        array $activityIds = [],
        ?string $email = null,
        array $companions = [],
    ): EventHelpRequestResponse
    {
        $event = $this->eventProvider->findPublished($eventIdentifier)
            ?? throw new BusinessRuleViolationException('Für diese Veranstaltung ist keine Helferanmeldung verfügbar.');
        if ($event->isHelpRegistrationClosed($this->clock->now())) {
            throw new BusinessRuleViolationException('Die Helferanmeldung für diese Veranstaltung ist geschlossen.');
        }
        if (count($companions) > self::MAX_COMPANIONS) {
            throw new BusinessRuleViolationException(sprintf('Es können höchstens %d weitere Personen mitgebracht werden.', self::MAX_COMPANIONS));
        }
        $activityIds = array_values(array_unique(array_map('trim', $activityIds)));
        $allowedIds = array_map(
            static fn ($assignment): string => $assignment->activityId,
            array_filter($event->activities, static fn ($assignment): bool => $assignment->selectable),
        );
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
        // Mitgebrachte Personen übernehmen die Aktivitäten der anmeldenden Person und belegen je einen eigenen Platz.
        $personCount = 1 + count($companions);
        foreach ($event->activities as $assignment) {
            if ($assignment->requiredHelpers !== null
                && in_array($assignment->activityId, $activityIds, true)
                && ($registrationCounts[$assignment->activityId] ?? 0) + $personCount > $assignment->requiredHelpers) {
                throw new BusinessRuleViolationException($personCount > 1
                    ? 'Für eine ausgewählte Aktivität sind nicht mehr genügend Plätze für alle angemeldeten Personen frei.'
                    : 'Eine ausgewählte Aktivität ist bereits vollständig belegt.');
            }
        }
        $selectedActivities = array_map(function (string $activityId): EventActivity {
            $activity = $this->activityManager->get($activityId);
            if (!$activity->active) {
                throw new BusinessRuleViolationException('Eine ausgewählte Aktivität ist nicht mehr verfügbar.');
            }

            return $activity;
        }, $activityIds);

        $trimmedFirstName = trim($firstName);
        $trimmedLastName = trim($lastName);
        $trimmedEmail = $email !== null && trim($email) !== '' ? trim($email) : null;
        $response = $this->register($event, $trimmedFirstName, $trimmedLastName, trim($message), $birthDate, $selectedActivities, $trimmedEmail);
        foreach ($companions as $companion) {
            $this->register(
                $event,
                trim($companion->firstName),
                trim($companion->lastName),
                sprintf('Mitgebracht von %s %s.', $trimmedFirstName, $trimmedLastName),
                $companion->birthDate,
                $selectedActivities,
                null,
            );
        }
        $this->notifier->helpRequestSubmitted(new EventHelpRequestSubmission(
            event: $event,
            firstName: $trimmedFirstName,
            lastName: $trimmedLastName,
            companionNames: array_map(
                static fn (EventHelpCompanionInput $companion): string => trim($companion->firstName).' '.trim($companion->lastName),
                $companions,
            ),
            activityNames: array_map(static fn (EventActivity $activity): string => $activity->name, $selectedActivities),
            message: trim($message),
            email: $trimmedEmail,
            memberNumber: $response->memberNumber,
        ));

        return $response;
    }

    /** @param list<EventActivity> $activities */
    private function register(
        VolunteerEvent $event,
        string $firstName,
        string $lastName,
        string $message,
        \DateTimeImmutable $birthDate,
        array $activities,
        ?string $email,
    ): EventHelpRequestResponse
    {
        $now = $this->clock->now();
        $member = $this->memberMatcher->match($firstName, $lastName, $birthDate, $email);
        $request = new EventHelpRequest(
            id: $this->identifierGenerator->generate(),
            eventIdentifier: $event->identifier,
            eventTitle: $event->title,
            eventDate: $event->date,
            eventTime: $event->time,
            firstName: $firstName,
            lastName: $lastName,
            message: $message,
            status: EventHelpRequestStatus::New,
            participationMinutes: null,
            participationIntervals: [],
            selectedActivities: array_map(
                fn (EventActivity $activity): SelectedEventActivity => new SelectedEventActivity($this->identifierGenerator->generate(), $activity->id, $activity->name),
                $activities,
            ),
            submittedAt: $now,
            updatedAt: $now,
            email: $email,
            birthDate: $birthDate,
            memberId: $member?->id,
        );

        $response = null;
        if ($member !== null) {
            // Bewusst je Person neu geladen: Eine zuvor in dieser Anfrage gespeicherte oder
            // zusammengeführte Anmeldung (z. B. derselben mitgebrachten Person doppelt) muss berücksichtigt werden.
            $existing = $this->duplicateMerger->findExisting($this->manager->all(), $member->id, $event->identifier);
            if ($existing !== []) {
                // Für dieses Mitglied besteht zu dieser Veranstaltung bereits eine Anmeldung — statt
                // eines weiteren Datensatzes werden die hier gewählten Aktivitäten in die jüngste
                // bestehende Anmeldung übernommen (mehrere bereits bestehende Duplikate werden dabei
                // gleich mit zusammengeführt); `$request` selbst wird nicht gespeichert. War die bestehende
                // Anmeldung als „Nicht teilgenommen“ erfasst, ist sie durch die neue Anfrage wieder offen.
                $survivor = count($existing) > 1 ? $this->duplicateMerger->merge($existing, $now) : $existing[0];
                $merged = $survivor->mergedWith($request, $now, $this->identifierGenerator)->reopen($now);
                $response = EventHelpRequestResponse::fromRequest($this->manager->save($merged), memberNumber: $member->memberNumber);
            }
        }
        $response ??= EventHelpRequestResponse::fromRequest($this->manager->save($request), memberNumber: $member?->memberNumber);

        // Nur beim automatischen Matching hier beim Absenden, nicht beim nachträglichen manuellen
        // Verknüpfen in der Verwaltung (siehe `EventHelpRequestConfirmationMailer`).
        $this->confirmationMailer->send($firstName, $lastName, $event, $member, $email);

        return $response;
    }
}
