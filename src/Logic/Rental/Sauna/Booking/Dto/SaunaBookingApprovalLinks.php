<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SaunaBookingApprovalLinks
{
    public function __construct(
        public string $approval,
        public string $rejection,
        public string $overview,
    ) {
    }
}
