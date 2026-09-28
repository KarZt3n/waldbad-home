<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;

/**
 * Adapter zum Mailversand der Einstellungen: meldet eine neu eingegangene Sauna-Anmeldung an die
 * dafür konfigurierten Empfänger, ohne dass die Vermietung Mailvorlagen oder Empfängerlisten kennt.
 */
interface SaunaBookingNotifierInterface
{
    public function bookingSubmitted(SaunaBooking $booking): void;
}
