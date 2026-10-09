<?php

namespace App\Logic\Membership\WorkAssignment\Model;

/**
 * Teilnahme eines Mitglieds an einer Veranstaltung bzw. einem Arbeitseinsatz mit den erfassten
 * Hilfezeiträumen — aus Sicht der Mitgliederverwaltung (siehe `MemberWorkAssignmentProviderInterface`).
 */
readonly class WorkAssignmentParticipation
{
    /** @param list<WorkAssignmentInterval> $intervals */
    public function __construct(
        public string $memberId,
        public string $eventTitle,
        public string $eventDate,
        public string $eventTime,
        public int $minutes,
        public array $intervals,
    ) {
    }

    public function year(): int
    {
        return (int) substr($this->eventDate, 0, 4);
    }
}
