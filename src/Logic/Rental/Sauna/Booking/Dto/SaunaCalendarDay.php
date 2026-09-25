<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SaunaCalendarDay
{
    /**
     * @param list<SaunaCalendarSlot> $slots
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public array $slots,
    ) {
    }
}
