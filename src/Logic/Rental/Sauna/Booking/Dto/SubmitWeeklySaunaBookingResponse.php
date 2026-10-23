<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SubmitWeeklySaunaBookingResponse
{
    /**
     * @param non-empty-list<\DateTimeImmutable> $bookedDates  angefragte Termine, je Termin eine Buchung
     * @param list<SaunaSkippedDate>             $skippedDates Termine der Serie, die nicht frei waren
     */
    public function __construct(
        public string $requestId,
        public array $bookedDates,
        public array $skippedDates,
    ) {
    }
}
