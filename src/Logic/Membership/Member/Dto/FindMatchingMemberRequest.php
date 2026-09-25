<?php

namespace App\Logic\Membership\Member\Dto;

readonly class FindMatchingMemberRequest
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $birthDate,
        public ?string $email = null,
    ) {
    }
}
