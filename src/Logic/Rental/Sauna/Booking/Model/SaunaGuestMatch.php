<?php

namespace App\Logic\Rental\Sauna\Booking\Model;

/** Ergebnis des Mitglieder-Abgleichs einer Sauna-Anmeldung (siehe `SaunaGuestMatcherInterface`). */
readonly class SaunaGuestMatch
{
    public function __construct(
        public string $memberId,
        public string $memberNumber,
    ) {
    }
}
