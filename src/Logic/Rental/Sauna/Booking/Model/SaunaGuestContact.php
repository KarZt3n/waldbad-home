<?php

namespace App\Logic\Rental\Sauna\Booking\Model;

/** Aktuelle Kontaktdaten des mit einer Sauna-Anmeldung verknüpften Mitglieds. */
readonly class SaunaGuestContact
{
    public function __construct(
        public string $memberNumber,
        public string $street,
        public string $postalCode,
        public string $city,
        public ?string $email,
    ) {
    }
}
