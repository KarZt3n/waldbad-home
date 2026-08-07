<?php

namespace App\Logic\Event\HelpRequest\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

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
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
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
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
        );
    }
}
