<?php

namespace App\Logic\Rental\Sauna\Booking\Service;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\SaunaGuestDirectoryInterface;

/**
 * E-Mail-Adresse der anfragenden Person für Bestätigungen (Annahme, Storno): die in einer der
 * Anfragen angegebene Adresse (die neueste zuerst), sonst die des zugeordneten Mitglieds.
 */
readonly class SaunaRequesterEmailResolver
{
    public function __construct(private SaunaGuestDirectoryInterface $guestDirectory)
    {
    }

    /**
     * @param non-empty-list<SaunaBooking> $bookings Anmeldungen derselben Person
     */
    public function resolve(array $bookings): ?string
    {
        $newestFirst = $bookings;
        usort($newestFirst, static fn (SaunaBooking $a, SaunaBooking $b): int => $b->submittedAt <=> $a->submittedAt);
        foreach ($newestFirst as $booking) {
            if ($booking->email !== null) {
                return $booking->email;
            }
        }
        $memberId = $bookings[0]->memberId;
        if ($memberId === null) {
            return null;
        }

        return $this->guestDirectory->contacts([$memberId])[$memberId]->email ?? null;
    }
}
