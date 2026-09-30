<?php

namespace App\Logic\Rental\Sauna\Booking\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/** Eine namentlich angegebene Person der Gruppe einer Sauna-Buchung (siehe `SaunaBooking::$participants`). */
readonly class SaunaBookingParticipant
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
    ) {
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw new BusinessRuleViolationException('Für jede Person der Gruppe sind Vorname und Nachname anzugeben.');
        }
        if (mb_strlen($this->firstName) > 120 || mb_strlen($this->lastName) > 120) {
            throw new BusinessRuleViolationException('Der Name einer Person der Gruppe überschreitet die erlaubte Länge.');
        }
    }
}
