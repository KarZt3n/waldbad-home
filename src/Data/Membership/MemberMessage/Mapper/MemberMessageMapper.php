<?php

namespace App\Data\Membership\MemberMessage\Mapper;

use App\Data\Membership\MemberMessage\Entity\MemberMessageEntity;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;

readonly class MemberMessageMapper
{
    public function toModel(MemberMessageEntity $entity): MemberMessage
    {
        return new MemberMessage(
            id: $entity->getId(),
            memberId: $entity->getMemberId(),
            memberNumber: $entity->getMemberNumber(),
            memberName: $entity->getMemberName(),
            message: $entity->getMessage(),
            status: MemberMessageStatus::from($entity->getStatus()),
            submittedAt: $entity->getSubmittedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function createEntity(MemberMessage $message): MemberMessageEntity
    {
        return new MemberMessageEntity(
            id: $message->id,
            memberId: $message->memberId,
            memberNumber: $message->memberNumber,
            memberName: $message->memberName,
            message: $message->message,
            status: $message->status->value,
            submittedAt: $message->submittedAt,
            updatedAt: $message->updatedAt,
        );
    }

    public function updateEntity(MemberMessage $message, MemberMessageEntity $entity): void
    {
        $entity->changeStatus($message->status->value, $message->updatedAt);
    }
}
