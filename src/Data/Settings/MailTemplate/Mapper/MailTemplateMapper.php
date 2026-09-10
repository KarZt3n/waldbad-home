<?php

namespace App\Data\Settings\MailTemplate\Mapper;

use App\Data\Settings\MailTemplate\Entity\MailTemplateEntity;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

readonly class MailTemplateMapper
{
    public function toModel(MailTemplateEntity $entity): MailTemplate
    {
        return new MailTemplate(
            key: MailTemplateKey::from($entity->getKey()),
            subject: $entity->getSubject(),
            body: $entity->getBody(),
            signatureId: $entity->getSignatureId(),
        );
    }

    public function createEntity(MailTemplate $template, \DateTimeImmutable $updatedAt): MailTemplateEntity
    {
        return new MailTemplateEntity(
            key: $template->key->value,
            subject: $template->subject,
            body: $template->body,
            signatureId: $template->signatureId,
            updatedAt: $updatedAt,
        );
    }

    public function updateEntity(MailTemplate $template, MailTemplateEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update($template->subject, $template->body, $template->signatureId, $updatedAt);
    }
}
