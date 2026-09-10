<?php

namespace App\Logic\Event\HelpRequest\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;

readonly class EventHelpRequest
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
        /** Angabe der/des Helfenden bei der Anmeldung, ob sie/er Vereinsmitglied ist — steuert, ob überhaupt versucht wird, die Anmeldung einem Mitgliedsdatensatz zuzuordnen (siehe `EventHelpRequestMemberMatcher`). */
        public bool $isMember = false,
        public ?string $email = null,
        public ?\DateTimeImmutable $birthDate = null,
        /** Verweis auf `Member::$id`, entweder automatisch beim Absenden ermittelt oder nachträglich manuell verknüpft (siehe `withMember()`). Kein DB-Fremdschlüssel, analog zu `Member::$payerMemberId`. */
        public ?string $memberId = null,
    ) {
        if (trim($this->eventIdentifier) === '' || trim($this->eventTitle) === '') {
            throw new BusinessRuleViolationException('Die Veranstaltung der Helferanmeldung ist ungültig.');
        }
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw new BusinessRuleViolationException('Vorname und Nachname sind erforderlich.');
        }
        if (mb_strlen($this->firstName) > 120 || mb_strlen($this->lastName) > 120 || mb_strlen($this->message) > 4000) {
            throw new BusinessRuleViolationException('Die Helferanmeldung überschreitet die erlaubte Länge.');
        }
        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die E-Mail-Adresse ist ungültig.');
        }
        if ($this->birthDate !== null && $this->birthDate > new \DateTimeImmutable('today')) {
            throw new BusinessRuleViolationException('Das Geburtsdatum darf nicht in der Zukunft liegen.');
        }
        if (count($this->participationIntervals) > 10) {
            throw new BusinessRuleViolationException('Pro Helfer können höchstens zehn Hilfezeiträume erfasst werden.');
        }
        if ($this->status === EventHelpRequestStatus::Participated && $this->participationIntervals === []) {
            throw new BusinessRuleViolationException('Für eine Teilnahme muss mindestens ein Hilfezeitraum erfasst werden.');
        }
        if ($this->status === EventHelpRequestStatus::NotParticipated
            && ($this->participationMinutes !== 0 || $this->participationIntervals !== [])) {
            throw new BusinessRuleViolationException('Eine Nichtteilnahme muss mit null Stunden erfasst werden.');
        }
        $sortedIntervals = $this->participationIntervals;
        usort($sortedIntervals, static fn (ParticipationInterval $left, ParticipationInterval $right): int => $left->startsAtMinute() <=> $right->startsAtMinute());
        $totalMinutes = 0;
        $previousEnd = 0;
        foreach ($sortedIntervals as $index => $interval) {
            if ($index > 0 && $interval->startsAtMinute() < $previousEnd) {
                throw new BusinessRuleViolationException('Hilfezeiträume dürfen sich nicht überschneiden.');
            }
            $totalMinutes += $interval->minutes;
            $previousEnd = $interval->endsAtMinute();
        }
        if ($this->status === EventHelpRequestStatus::Participated && $this->participationMinutes !== $totalMinutes) {
            throw new BusinessRuleViolationException('Die gespeicherte Gesamtzeit stimmt nicht mit den Hilfezeiträumen überein.');
        }
    }

    /**
     * @param list<ParticipationInterval> $participationIntervals
     */
    public function recordParticipation(
        bool $participated,
        array $participationIntervals,
        \DateTimeImmutable $updatedAt,
    ): self
    {
        return new self(
            id: $this->id,
            eventIdentifier: $this->eventIdentifier,
            eventTitle: $this->eventTitle,
            eventDate: $this->eventDate,
            eventTime: $this->eventTime,
            firstName: $this->firstName,
            lastName: $this->lastName,
            message: $this->message,
            status: $participated ? EventHelpRequestStatus::Participated : EventHelpRequestStatus::NotParticipated,
            participationMinutes: $participated ? array_sum(array_map(static fn (ParticipationInterval $interval): int => $interval->minutes, $participationIntervals)) : 0,
            participationIntervals: $participated ? $participationIntervals : [],
            selectedActivities: $this->selectedActivities,
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
            isMember: $this->isMember,
            email: $this->email,
            birthDate: $this->birthDate,
            memberId: $this->memberId,
        );
    }

    /**
     * Korrigiert Vor-/Nachname nachträglich in der Verwaltung — z. B. wenn ein Tippfehler bei der
     * Anmeldung das automatische Zuordnen zu einem Mitglied verhindert hat (siehe
     * `EventHelpRequestMemberMatcher`) und der Name manuell aus dem verknüpften Mitgliedsdatensatz
     * übernommen wird.
     */
    public function withIdentity(string $firstName, string $lastName, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            eventIdentifier: $this->eventIdentifier,
            eventTitle: $this->eventTitle,
            eventDate: $this->eventDate,
            eventTime: $this->eventTime,
            firstName: $firstName,
            lastName: $lastName,
            message: $this->message,
            status: $this->status,
            participationMinutes: $this->participationMinutes,
            participationIntervals: $this->participationIntervals,
            selectedActivities: $this->selectedActivities,
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
            isMember: $this->isMember,
            email: $this->email,
            birthDate: $this->birthDate,
            memberId: $this->memberId,
        );
    }

    /**
     * Verknüpft (oder löst, bei `null`) die Zuordnung zu einem Mitgliedsdatensatz — entweder
     * automatisch beim Absenden ermittelt (`EventHelpRequestMemberMatcher`) oder nachträglich
     * manuell in der Verwaltung gesetzt (siehe `LinkEventHelpRequestMemberUseCase`, das dieselbe
     * Aktion auch zur Korrektur einer falschen Verknüpfung nutzt).
     */
    public function withMember(?string $memberId, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            eventIdentifier: $this->eventIdentifier,
            eventTitle: $this->eventTitle,
            eventDate: $this->eventDate,
            eventTime: $this->eventTime,
            firstName: $this->firstName,
            lastName: $this->lastName,
            message: $this->message,
            status: $this->status,
            participationMinutes: $this->participationMinutes,
            participationIntervals: $this->participationIntervals,
            selectedActivities: $this->selectedActivities,
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
            isMember: $this->isMember,
            email: $this->email,
            birthDate: $this->birthDate,
            memberId: $memberId,
        );
    }

    /**
     * Führt eine zweite Anmeldung desselben Mitglieds zur selben Veranstaltung in diese hier ein
     * (siehe `EventHelpRequestDuplicateMerger`) — z. B. weil sich jemand aus Versehen zweimal
     * angemeldet hat, oder weil eine neue Anmeldung eingeht, obwohl für das automatisch/manuell
     * zugeordnete Mitglied zu dieser Veranstaltung bereits eine Anmeldung besteht. `$other` bleibt
     * unverändert — die aufrufende Seite entscheidet, ob/wie sie danach gelöscht wird. Eigene
     * Kennung, Veranstaltungsdaten, Name und Mitgliedszuordnung bleiben die von `$this` (siehe
     * `EventHelpRequestDuplicateMerger::merge()`, das dafür stets die jüngste Anmeldung als `$this`
     * verwendet); nur Aktivitäten und — falls `$other` teilgenommen hat — deren Hilfezeiträume
     * werden übernommen.
     *
     * Aktivitäten und Hilfezeiträume bekommen dabei durchweg frische Kennungen (auch die bereits zu
     * `$this` gehörenden) statt ihre bisherigen IDs zu behalten: Sowohl `$this` als auch `$other`
     * sind zu diesem Zeitpunkt bereits geladene, in der Persistenzschicht bekannte Datensätze — eine
     * Wiederverwendung ihrer IDs würde beim Speichern mit den noch referenzierten alten Entities
     * kollidieren (siehe `EventHelpRequestMapper::updateEntity()`, `replaceSelectedActivities()`/
     * `replaceParticipationIntervals()`).
     */
    public function mergedWith(self $other, \DateTimeImmutable $updatedAt, IdentifierGeneratorInterface $identifierGenerator): self
    {
        $activityIds = array_map(static fn (SelectedEventActivity $activity): string => $activity->activityId, $this->selectedActivities);
        $mergedActivities = $this->selectedActivities;
        foreach ($other->selectedActivities as $activity) {
            if (!in_array($activity->activityId, $activityIds, true)) {
                $mergedActivities[] = $activity;
                $activityIds[] = $activity->activityId;
            }
        }
        $mergedActivities = array_map(
            static fn (SelectedEventActivity $activity): SelectedEventActivity => new SelectedEventActivity(
                $identifierGenerator->generate(),
                $activity->activityId,
                $activity->activityName,
            ),
            $mergedActivities,
        );

        $participated = $this->status === EventHelpRequestStatus::Participated || $other->status === EventHelpRequestStatus::Participated;
        $mergedIntervals = [
            ...($this->status === EventHelpRequestStatus::Participated ? $this->participationIntervals : []),
            ...($other->status === EventHelpRequestStatus::Participated ? $other->participationIntervals : []),
        ];
        // Positionen fortlaufend neu vergeben, da sie sich sonst über beide Anmeldungen hinweg
        // wiederholen könnten; die Uhrzeiten selbst bleiben unverändert.
        $mergedIntervals = array_map(
            static fn (ParticipationInterval $interval, int $position): ParticipationInterval => new ParticipationInterval(
                id: $identifierGenerator->generate(),
                position: $position,
                fromTime: $interval->fromTime,
                toTime: $interval->toTime,
            ),
            $mergedIntervals,
            array_keys($mergedIntervals),
        );
        $totalMinutes = array_sum(array_map(static fn (ParticipationInterval $interval): int => $interval->minutes, $mergedIntervals));

        return new self(
            id: $this->id,
            eventIdentifier: $this->eventIdentifier,
            eventTitle: $this->eventTitle,
            eventDate: $this->eventDate,
            eventTime: $this->eventTime,
            firstName: $this->firstName,
            lastName: $this->lastName,
            message: $this->message,
            status: $participated ? EventHelpRequestStatus::Participated : $this->status,
            participationMinutes: $participated ? $totalMinutes : $this->participationMinutes,
            participationIntervals: $participated ? $mergedIntervals : $this->participationIntervals,
            selectedActivities: $mergedActivities,
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
            isMember: $this->isMember,
            email: $this->email,
            birthDate: $this->birthDate,
            memberId: $this->memberId,
        );
    }
}
