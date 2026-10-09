<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;

/** Schlanker Suchtreffer für andere Module (z. B. Sauna-Dienstplan): ohne Bank-, Beitrags- oder Kontaktdaten. */
readonly class MemberSearchResultResponse
{
    public function __construct(
        public string $id,
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
        public string $street,
        public \DateTimeImmutable $birthDate,
        public bool $boardMember,
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
            birthDate: $member->birthDate,
            boardMember: in_array($member->function, [MemberFunction::Board, MemberFunction::Treasurer], true),
        );
    }
}
