<?php

namespace App\Logic\Membership\Application\Dto;

use App\Logic\Membership\Member\Model\Salutation;

readonly class ApplicantInput
{
    public function __construct(
        public Salutation $salutation,
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
