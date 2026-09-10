<?php

namespace App\Data\Settings\MailSignature\Mapper;

use App\Data\Settings\MailSignature\Entity\MailSignatureEntity;
use App\Logic\Settings\MailSignature\Model\MailSignature;

readonly class MailSignatureMapper
{
    public function toModel(MailSignatureEntity $entity): MailSignature
    {
        return new MailSignature(
            id: $entity->getId(),
            name: $entity->getName(),
            body: $entity->getBody(),
        );
    }

    public function createEntity(MailSignature $signature): MailSignatureEntity
    {
        return new MailSignatureEntity(
            id: $signature->id,
            name: $signature->name,
            body: $signature->body,
        );
    }

    public function updateEntity(MailSignature $signature, MailSignatureEntity $entity): void
    {
        $entity->update($signature->name, $signature->body);
    }
}
