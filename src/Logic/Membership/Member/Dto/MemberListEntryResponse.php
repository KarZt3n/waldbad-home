<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\Member\Model\MemberListEntry;

readonly class MemberListEntryResponse
{
    public function __construct(
        public string $id,
        public string $memberNumber,
        public string $primaryMemberNumber,
        public string $lastName,
        public string $firstName,
        public \DateTimeImmutable $birthDate,
        public string $street,
        public string $city,
        public ?\DateTimeImmutable $leftAt,
        public bool $active,
    ) {
    }

    public static function fromEntry(MemberListEntry $entry): self
    {
        return new self(
            id: $entry->id,
            memberNumber: $entry->memberNumber,
            primaryMemberNumber: $entry->primaryMemberNumber,
            lastName: $entry->lastName,
            firstName: $entry->firstName,
            birthDate: $entry->birthDate,
            street: $entry->street,
            city: $entry->city,
            leftAt: $entry->leftAt,
            active: $entry->isActive(new \DateTimeImmutable('today')),
        );
    }
}
