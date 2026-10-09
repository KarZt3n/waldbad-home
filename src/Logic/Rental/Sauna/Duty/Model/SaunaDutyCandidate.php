<?php

namespace App\Logic\Rental\Sauna\Duty\Model;

/** Person, die für den Saunadienst gesucht und eingetragen werden kann (aus der Mitgliederverwaltung). */
readonly class SaunaDutyCandidate
{
    public function __construct(
        public string $memberId,
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
        public string $street,
        public ?\DateTimeImmutable $birthDate,
        public bool $boardMember,
    ) {
    }
}
