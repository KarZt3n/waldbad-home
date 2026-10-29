<?php

namespace App\Logic\Membership\Application\Dto;

/** Ein bisheriges Mitglied des Haushalts, das im Antrag nicht mehr vorkommt. */
readonly class MissingHouseholdMemberResponse
{
    public function __construct(
        public string $memberId,
        public string $memberNumber,
        public string $name,
        public string $birthDate,
        public string $familyRole,
        /** Grund, warum das Mitglied bei der Übernahme nicht ausgetragen werden kann, oder `null`. */
        public ?string $removalBlocker,
    ) {
    }
}
