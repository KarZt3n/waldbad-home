<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SaunaParticipantInput
{
    public function __construct(
        public string $firstName,
        public string $lastName,
    ) {
    }
}
