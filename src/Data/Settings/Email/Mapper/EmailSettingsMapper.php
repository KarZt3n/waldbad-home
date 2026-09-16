<?php

namespace App\Data\Settings\Email\Mapper;

use App\Data\Settings\Email\Entity\EmailSettingsEntity;
use App\Logic\Settings\Email\Model\EmailSettings;

readonly class EmailSettingsMapper
{
    public function toModel(EmailSettingsEntity $entity): EmailSettings
    {
        return new EmailSettings(notificationRecipients: $entity->getNotificationRecipients());
    }

    public function createEntity(EmailSettings $settings, \DateTimeImmutable $updatedAt): EmailSettingsEntity
    {
        return new EmailSettingsEntity(
            id: EmailSettingsEntity::ID,
            notificationRecipients: $settings->notificationRecipients,
            updatedAt: $updatedAt,
        );
    }

    public function updateEntity(EmailSettings $settings, EmailSettingsEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update($settings->notificationRecipients, $updatedAt);
    }
}
