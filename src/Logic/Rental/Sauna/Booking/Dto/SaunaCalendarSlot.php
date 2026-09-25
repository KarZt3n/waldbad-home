<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

use App\Logic\Rental\Sauna\Booking\Model\SaunaSlotState;

readonly class SaunaCalendarSlot
{
    public function __construct(
        public string $startTime,
        public string $endTime,
        public SaunaSlotState $state,
    ) {
    }
}
