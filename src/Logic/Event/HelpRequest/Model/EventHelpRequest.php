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
        /** Angabe der/des Helfenden bei der Anmeldung, ob sie/er Vereinsmitglied ist. Wird aktuell nicht mehr über das öffentliche Formular erfasst; das Feld bleibt für bestehende Daten sowie eine mögliche künftige Nutzung erhalten. */
        public bool $isMember = false,
        public ?string $email = null,
        public ?\DateTimeImmutable $birthDate = null,
        /** Verweis auf `Member::$id`, entweder automatisch beim Absenden ermittelt (siehe `MemberIdentityMatcher`) oder nachträglich manuell verknüpft (siehe `withMember()`). Kein DB-Fremdschlüssel, analog zu `Member::$payerMemberId`. */
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
        $openIntervalCount = count(array_filter($this->participationIntervals, static fn (ParticipationInterval $interval): bool => $interval->isOpen()));
        if ($this->status === EventHelpRequestStatus::Present && $openIntervalCount !== 1) {
            throw new BusinessRuleViolationException('Eine anwesende Person benötigt genau einen Hilfezeitraum ohne Bis-Uhrzeit.');
        }
        if ($this->status !== EventHelpRequestStatus::Present && $openIntervalCount > 0) {
            throw new BusinessRuleViolationException('Jeder Hilfezeitraum benötigt eine Bis-Uhrzeit, sofern die Person nicht mehr anwesend ist.');
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
        if (in_array($this->status, [EventHelpRequestStatus::Participated, EventHelpRequestStatus::Present], true)
            && $this->participationMinutes !== $totalMinutes) {
            throw new BusinessRuleViolationException('Die gespeicherte Gesamtzeit stimmt nicht mit den Hilfezeiträumen überein.');
        }
    }

    /**
     * Händische Erfassung bzw. Korrektur aller Zeiträume. Bleibt ein Zeitraum ohne Bis-Uhrzeit,
     * gilt die Person weiterhin als anwesend.
     *
     * @param list<ParticipationInterval> $participationIntervals
     */
    public function recordParticipation(
        bool $participated,
        array $participationIntervals,
        \DateTimeImmutable $updatedAt,
    ): self
    {
        return $participated
            ? $this->withParticipationIntervals($participationIntervals, $updatedAt)
            : $this->withParticipationState(EventHelpRequestStatus::NotParticipated, 0, [], $updatedAt);
    }

    public function openParticipationInterval(): ?ParticipationInterval
    {
        if ($this->status !== EventHelpRequestStatus::Present) {
            return null;
        }
        foreach ($this->participationIntervals as $interval) {
            if ($interval->isOpen()) {
                return $interval;
            }
        }

        return null;
    }

    /**
     * Markiert die Person ab `$fromTime` als anwesend. Fällt der Beginn in den letzten Zeitraum
     * (z. B. kurz nach dem aufgerundeten Austragen), wird dieser fortgesetzt; ab dessen Ende oder
     * später entsteht ein neuer Zeitraum, die bisherigen bleiben unverändert. Der fortgesetzte
     * Zeitraum erhält eine neue Kennung, da geänderte Zeiträume in der Persistenz neu angelegt
     * werden (siehe `EventHelpRequestMapper::syncedIntervalEntities()`).
     */
    public function checkIn(string $fromTime, string $intervalId, \DateTimeImmutable $updatedAt): self
    {
        if ($this->status === EventHelpRequestStatus::Present) {
            throw new BusinessRuleViolationException('Die Person ist bereits als anwesend markiert.');
        }
        if ($this->status === EventHelpRequestStatus::Resolved) {
            throw new BusinessRuleViolationException('Eine erledigte Helferanmeldung kann nicht als anwesend markiert werden.');
        }
        $intervals = $this->status === EventHelpRequestStatus::Participated ? $this->participationIntervals : [];
        $checkIn = new ParticipationInterval($intervalId, count($intervals), $fromTime, null);
        $latest = null;
        foreach ($intervals as $interval) {
            if ($latest === null || $interval->endsAtMinute() > $latest->endsAtMinute()) {
                $latest = $interval;
            }
        }
        if ($latest !== null && $checkIn->startsAtMinute() < $latest->endsAtMinute()) {
            $intervals = array_values(array_filter($intervals, static fn (ParticipationInterval $interval): bool => $interval !== $latest));
            $checkIn = new ParticipationInterval($intervalId, $latest->position, $latest->fromTime, null);
        }

        return $this->withParticipationIntervals([...$intervals, $checkIn], $updatedAt);
    }

    /**
     * Schließt den offenen Zeitraum mit `$toTime`. Er erhält eine neue Kennung, da geänderte
     * Zeiträume in der Persistenz neu angelegt werden (siehe `checkIn()`).
     */
    public function checkOut(string $toTime, string $intervalId, \DateTimeImmutable $updatedAt): self
    {
        $open = $this->openParticipationInterval();
        if ($open === null) {
            throw new BusinessRuleViolationException('Die Person ist nicht als anwesend markiert.');
        }
        $intervals = array_map(
            static fn (ParticipationInterval $interval): ParticipationInterval => $interval === $open
                ? new ParticipationInterval($intervalId, $interval->position, $interval->fromTime, $toTime)
                : $interval,
            $this->participationIntervals,
        );

        return $this->withParticipationIntervals($intervals, $updatedAt);
    }

    /**
     * @param list<ParticipationInterval> $intervals
     */
    private function withParticipationIntervals(array $intervals, \DateTimeImmutable $updatedAt): self
    {
        $hasOpenInterval = array_filter($intervals, static fn (ParticipationInterval $interval): bool => $interval->isOpen()) !== [];

        return $this->withParticipationState(
            $hasOpenInterval ? EventHelpRequestStatus::Present : EventHelpRequestStatus::Participated,
            array_sum(array_map(static fn (ParticipationInterval $interval): int => $interval->minutes, $intervals)),
            $intervals,
            $updatedAt,
        );
    }

    /**
     * @param list<ParticipationInterval> $intervals
     */
    private function withParticipationState(
        EventHelpRequestStatus $status,
        int $minutes,
        array $intervals,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $this->id,
            eventIdentifier: $this->eventIdentifier,
            eventTitle: $this->eventTitle,
            eventDate: $this->eventDate,
            eventTime: $this->eventTime,
            firstName: $this->firstName,
            lastName: $this->lastName,
            message: $this->message,
            status: $status,
            participationMinutes: $minutes,
            participationIntervals: $intervals,
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
     * Meldet sich eine Person erneut zur selben Veranstaltung an, obwohl ihre Anmeldung bereits als
     * „Nicht teilgenommen“ erfasst wurde, ist die Anmeldung wieder offen. Andere Status bleiben
     * unverändert – insbesondere werden erfasste Teilnahmezeiten nie verworfen.
     */
    public function reopen(\DateTimeImmutable $updatedAt): self
    {
        if ($this->status !== EventHelpRequestStatus::NotParticipated) {
            return $this;
        }

        return new self(
            id: $this->id,
            eventIdentifier: $this->eventIdentifier,
            eventTitle: $this->eventTitle,
            eventDate: $this->eventDate,
            eventTime: $this->eventTime,
            firstName: $this->firstName,
            lastName: $this->lastName,
            message: $this->message,
            status: EventHelpRequestStatus::New,
            participationMinutes: null,
            participationIntervals: [],
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
     * `MemberIdentityMatcher`) und der Name manuell aus dem verknüpften Mitgliedsdatensatz
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
     * automatisch beim Absenden ermittelt (`MemberIdentityMatcher`) oder nachträglich
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

        $participatedStatuses = [EventHelpRequestStatus::Participated, EventHelpRequestStatus::Present];
        $participated = in_array($this->status, $participatedStatuses, true) || in_array($other->status, $participatedStatuses, true);
        $mergedIntervals = [
            ...(in_array($this->status, $participatedStatuses, true) ? $this->participationIntervals : []),
            ...(in_array($other->status, $participatedStatuses, true) ? $other->participationIntervals : []),
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
        $mergedStatus = array_filter($mergedIntervals, static fn (ParticipationInterval $interval): bool => $interval->isOpen()) !== []
            ? EventHelpRequestStatus::Present
            : EventHelpRequestStatus::Participated;

        return new self(
            id: $this->id,
            eventIdentifier: $this->eventIdentifier,
            eventTitle: $this->eventTitle,
            eventDate: $this->eventDate,
            eventTime: $this->eventTime,
            firstName: $this->firstName,
            lastName: $this->lastName,
            message: $this->message,
            status: $participated ? $mergedStatus : $this->status,
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
