<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

/**
 * Anfrage aus dem Kalender, die sich wöchentlich am selben Wochentag zur selben Zeit wiederholt:
 * `$firstBooking` beschreibt den ersten Termin, `$repeatUntil` den letzten möglichen Tag der Serie.
 */
readonly class SubmitWeeklySaunaBookingRequest
{
    public function __construct(
        public SubmitSaunaBookingRequest $firstBooking,
        public \DateTimeImmutable $repeatUntil,
    ) {
    }
}
