<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\Member\Model\Member;

readonly class MatchedMemberResponse
{
    public function __construct(
        public string $id,
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
    ) {
    }

    public static function fromMember(Member $member): self
    {
        return new self(
            id: $member->id,
            memberNumber: $member->memberNumber,
            firstName: $member->firstName,
            lastName: $member->lastName,
        );
    }
}
