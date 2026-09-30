<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;

interface SaunaBookingProcessorInterface
{
    public function save(SaunaBooking $booking): SaunaBooking;

    /**
     * Speichert mehrere neue oder bestehende Buchungen gemeinsam oder gar nicht (siehe
     * `SubmitIndividualSaunaRequestUseCase`, `SaunaBookingAcceptance`).
     *
     * @param list<SaunaBooking> $bookings
     *
     * @return list<SaunaBooking>
     */
    public function saveAll(array $bookings): array;
}
