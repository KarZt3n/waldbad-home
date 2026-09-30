<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SubmitIndividualSaunaRequest
{
    /**
     * @param non-empty-list<SaunaRequestDayInput> $days
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $birthDate,
        public ?string $email,
        public string $message,
        public array $days,
    ) {
    }
}
