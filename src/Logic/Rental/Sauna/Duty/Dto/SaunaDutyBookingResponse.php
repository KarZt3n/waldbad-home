<?php

namespace App\Logic\Rental\Sauna\Duty\Dto;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;

/** Buchung, wie sie im Dienstplan an einem Dienstzeitpunkt angezeigt wird. */
readonly class SaunaDutyBookingResponse
{
    public function __construct(
        public string $id,
        public string $startTime,
        public string $endTime,
        public int $personCount,
        public string $firstName,
        public string $lastName,
        public SaunaBookingStatus $status,
    ) {
    }

    public static function fromBooking(SaunaBooking $booking): self
    {
        return new self(
            $booking->id,
            $booking->startTime,
            $booking->endTime,
            $booking->personCount,
            $booking->firstName,
            $booking->lastName,
            $booking->status,
        );
    }
}
