<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SubmitSaunaBookingRequest
{
    /**
     * @param list<SaunaParticipantInput> $participants Namen aller Personen der Gruppe, die anfragende Person zuerst
     */
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
        public array $participants,
    ) {
    }
}
