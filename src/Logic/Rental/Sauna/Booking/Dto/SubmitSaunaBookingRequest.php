<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SubmitSaunaBookingRequest
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $startTime,
        public string $endTime,
        public int $personCount,
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $birthDate,
        public ?string $email,
        public string $message,
        public bool $individual = false,
    ) {
    }
}
