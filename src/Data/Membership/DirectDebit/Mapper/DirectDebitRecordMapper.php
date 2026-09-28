<?php

namespace App\Data\Membership\DirectDebit\Mapper;

use App\Data\Membership\DirectDebit\Entity\DirectDebitRecordEntity;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Logic\Membership\DirectDebit\Model\SequenceType;

readonly class DirectDebitRecordMapper
{
    public function toModel(DirectDebitRecordEntity $entity): DirectDebitRecord
    {
        return new DirectDebitRecord(
            id: $entity->getId(),
            payerMemberId: $entity->getPayerMemberId(),
            mandateReference: $entity->getMandateReference(),
            contributionYear: $entity->getContributionYear(),
            sequenceType: SequenceType::from($entity->getSequenceType()),
            collectionDate: $entity->getCollectionDate(),
            amountCents: $entity->getAmountCents(),
            messageId: $entity->getMessageId(),
            exportedAt: $entity->getExportedAt(),
        );
    }

    public function createEntity(DirectDebitRecord $record): DirectDebitRecordEntity
    {
        return new DirectDebitRecordEntity(
            id: $record->id,
            payerMemberId: $record->payerMemberId,
            mandateReference: $record->mandateReference,
            contributionYear: $record->contributionYear,
            sequenceType: $record->sequenceType->value,
            collectionDate: $record->collectionDate,
            amountCents: $record->amountCents,
            messageId: $record->messageId,
            exportedAt: $record->exportedAt,
        );
    }
}
