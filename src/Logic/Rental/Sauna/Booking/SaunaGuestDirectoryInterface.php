<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestContact;

/** Adapter zum Mitgliedermodul: Kontaktdaten der mit Sauna-Anmeldungen verknüpften Mitglieder. */
interface SaunaGuestDirectoryInterface
{
    /**
     * @param list<string> $memberIds
     * @return array<string, SaunaGuestContact> nach Mitglieds-ID; unbekannte IDs fehlen
     */
    public function contacts(array $memberIds): array;
}
