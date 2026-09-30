<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;

/**
 * Adapter zum Mailversand der Einstellungen: meldet eine neu eingegangene Sauna-Anmeldung an die
 * dafür konfigurierten Empfänger und bestätigt der anfragenden Person Annahme bzw. Storno, ohne dass
 * die Vermietung Mailvorlagen oder Empfängerlisten kennt.
 */
interface SaunaBookingNotifierInterface
{
    public function bookingSubmitted(SaunaBooking $booking): void;

    /**
     * Eine individuelle Anfrage mit einem oder mehreren Wunschtagen (je Tag eine Buchung) als eine
     * gemeinsame Benachrichtigung.
     *
     * @param non-empty-list<SaunaBooking> $bookings
     */
    public function individualRequestSubmitted(array $bookings): void;

    /**
     * Bestätigung an die anfragende Person — bei „Alle annehmen“ einer individuellen Anfrage mit
     * allen angenommenen Tagen in einer Mail.
     *
     * @param non-empty-list<SaunaBooking> $bookings
     */
    public function bookingsAccepted(array $bookings, string $recipientEmail): void;

    /** Stornobestätigung eines zuvor angenommenen Termins an die anfragende Person. */
    public function bookingCancelled(SaunaBooking $booking, string $recipientEmail): void;
}
