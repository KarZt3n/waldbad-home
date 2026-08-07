<?php

namespace App\Logic\Membership\Application\Dto;

readonly class ApplicantInput
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $birthDate,
        public string $street,
        public string $houseNumber,
        public string $postalCode,
        public string $city,
        public ?string $phone,
        public ?string $email,
    ) {
    }
}
