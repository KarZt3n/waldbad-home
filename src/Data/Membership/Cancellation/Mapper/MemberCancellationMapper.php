<?php

namespace App\Data\Membership\Cancellation\Mapper;

use App\Data\Membership\Cancellation\Entity\MemberCancellationEntity;
use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Membership\Cancellation\Model\MemberCancellationStatus;

readonly class MemberCancellationMapper
{
    public function toModel(MemberCancellationEntity $entity): MemberCancellation
    {
        return new MemberCancellation(
            id: $entity->getId(),
            memberId: $entity->getMemberId(),
            memberNumber: $entity->getMemberNumber(),
            memberName: $entity->getMemberName(),
            primaryMemberNumber: $entity->getPrimaryMemberNumber(),
            requestedByEmail: $entity->getRequestedByEmail(),
            requestedByName: $entity->getRequestedByName(),
            requestedByMemberNumber: $entity->getRequestedByMemberNumber(),
            submittedAt: $entity->getSubmittedAt(),
            exitDate: $entity->getExitDate(),
            successorMemberId: $entity->getSuccessorMemberId(),
            successorName: $entity->getSuccessorName(),
            contactRecipientMemberId: $entity->getContactRecipientMemberId(),
            contactRecipientName: $entity->getContactRecipientName(),
            status: MemberCancellationStatus::from($entity->getStatus()),
            decidedAt: $entity->getDecidedAt(),
        );
    }

    public function createEntity(MemberCancellation $cancellation): MemberCancellationEntity
    {
        return new MemberCancellationEntity(
            id: $cancellation->id,
            memberId: $cancellation->memberId,
            memberNumber: $cancellation->memberNumber,
            memberName: $cancellation->memberName,
            primaryMemberNumber: $cancellation->primaryMemberNumber,
            requestedByEmail: $cancellation->requestedByEmail,
            requestedByName: $cancellation->requestedByName,
            requestedByMemberNumber: $cancellation->requestedByMemberNumber,
            submittedAt: $cancellation->submittedAt,
            exitDate: $cancellation->exitDate,
            successorMemberId: $cancellation->successorMemberId,
            successorName: $cancellation->successorName,
            contactRecipientMemberId: $cancellation->contactRecipientMemberId,
            contactRecipientName: $cancellation->contactRecipientName,
            status: $cancellation->status->value,
            decidedAt: $cancellation->decidedAt,
        );
    }

    public function updateEntity(MemberCancellation $cancellation, MemberCancellationEntity $entity): void
    {
        $entity->decide($cancellation->status->value, $cancellation->decidedAt);
    }
}
