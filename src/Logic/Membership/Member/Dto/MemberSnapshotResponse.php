<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\Member\Model\MemberSnapshot;

readonly class MemberSnapshotResponse
{
    public function __construct(
        public MemberResponse $member,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $deletedAt,
    ) {
    }

    public static function fromSnapshot(MemberSnapshot $snapshot): self
    {
        return new self(MemberResponse::fromMember($snapshot->member), $snapshot->createdAt, $snapshot->updatedAt, $snapshot->deletedAt);
    }
}
