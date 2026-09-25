<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\Member\Model\Member;

readonly class MemberContactResponse
{
    public function __construct(
        public string $id,
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
        public string $street,
        public string $postalCode,
        public string $city,
        public ?string $email,
    ) {
    }

    public static function fromMember(Member $member): self
    {
        return new self(
            id: $member->id,
            memberNumber: $member->memberNumber,
            firstName: $member->firstName,
            lastName: $member->lastName,
            street: $member->street,
            postalCode: $member->postalCode,
            city: $member->city,
            email: $member->email,
        );
    }
}
