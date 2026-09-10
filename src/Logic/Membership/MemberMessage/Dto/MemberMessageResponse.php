<?php

namespace App\Logic\Membership\MemberMessage\Dto;

use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;

readonly class MemberMessageResponse
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $memberNumber,
        public string $memberName,
        public string $message,
        public MemberMessageStatus $status,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromMessage(MemberMessage $message): self
    {
        return new self(
            id: $message->id,
            memberId: $message->memberId,
            memberNumber: $message->memberNumber,
            memberName: $message->memberName,
            message: $message->message,
            status: $message->status,
            submittedAt: $message->submittedAt,
            updatedAt: $message->updatedAt,
        );
    }
}
