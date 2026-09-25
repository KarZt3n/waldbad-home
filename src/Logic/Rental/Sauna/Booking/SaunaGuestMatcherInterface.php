<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestMatch;

/**
 * Adapter zum Mitgliedermodul: ordnet die Angaben einer Sauna-Anmeldung einem Mitglied zu, ohne
 * dass die Vermietung die internen Strukturen der Mitgliederverwaltung kennt.
 */
interface SaunaGuestMatcherInterface
{
    public function match(string $firstName, string $lastName, \DateTimeImmutable $birthDate, ?string $email): ?SaunaGuestMatch;
}
