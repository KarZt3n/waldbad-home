<?php

namespace App\Data\Membership\WorkAssignmentRefund\Mapper;

use App\Data\Membership\WorkAssignmentRefund\Entity\WorkAssignmentRefundRecordEntity;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;

readonly class WorkAssignmentRefundRecordMapper
{
    public function toModel(WorkAssignmentRefundRecordEntity $entity): WorkAssignmentRefundRecord
    {
        return new WorkAssignmentRefundRecord(
            id: $entity->getId(),
            primaryMemberNumber: $entity->getPrimaryMemberNumber(),
            payerMemberId: $entity->getPayerMemberId(),
            year: $entity->getYear(),
            amountCents: $entity->getAmountCents(),
            messageId: $entity->getMessageId(),
            exportedAt: $entity->getExportedAt(),
        );
    }

    public function createEntity(WorkAssignmentRefundRecord $record): WorkAssignmentRefundRecordEntity
    {
        return new WorkAssignmentRefundRecordEntity(
            id: $record->id,
            primaryMemberNumber: $record->primaryMemberNumber,
            payerMemberId: $record->payerMemberId,
            year: $record->year,
            amountCents: $record->amountCents,
            messageId: $record->messageId,
            exportedAt: $record->exportedAt,
        );
    }
}
