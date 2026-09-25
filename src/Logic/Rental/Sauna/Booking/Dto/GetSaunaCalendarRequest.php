<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class GetSaunaCalendarRequest
{
    public function __construct(
        public \DateTimeImmutable $from,
        public int $days,
    ) {
    }
}
