<?php

namespace App\Data\Membership\DirectDebit\Mapper;

use App\Data\Membership\DirectDebit\Entity\DirectDebitCreditorEntity;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;

readonly class DirectDebitCreditorMapper
{
    public function toModel(DirectDebitCreditorEntity $entity): DirectDebitCreditor
    {
        return new DirectDebitCreditor($entity->getName(), $entity->getCreditorId(), $entity->getIban(), $entity->getBic());
    }

    public function createEntity(DirectDebitCreditor $creditor): DirectDebitCreditorEntity
    {
        return new DirectDebitCreditorEntity(DirectDebitCreditorEntity::ID, $creditor->name, $creditor->creditorId, $creditor->iban, $creditor->bic);
    }

    public function updateEntity(DirectDebitCreditor $creditor, DirectDebitCreditorEntity $entity): void
    {
        $entity->update($creditor->name, $creditor->creditorId, $creditor->iban, $creditor->bic);
    }
}
