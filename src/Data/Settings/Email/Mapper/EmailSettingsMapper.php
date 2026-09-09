<?php

namespace App\Data\Settings\Email\Mapper;

use App\Data\Settings\Email\Entity\EmailSettingsEntity;
use App\Logic\Settings\Email\Model\EmailProviderPreset;
use App\Logic\Settings\Email\Model\EmailSettings;

readonly class EmailSettingsMapper
{
    public function toModel(EmailSettingsEntity $entity): EmailSettings
    {
        return new EmailSettings(
            provider: $entity->getProvider() === null ? null : EmailProviderPreset::from($entity->getProvider()),
            host: $entity->getHost(),
            port: $entity->getPort(),
            username: $entity->getUsername(),
            password: $entity->getPassword(),
            fromAddress: $entity->getFromAddress(),
            fromName: $entity->getFromName(),
            notificationRecipients: $entity->getNotificationRecipients(),
        );
    }

    public function createEntity(EmailSettings $settings, \DateTimeImmutable $updatedAt): EmailSettingsEntity
    {
        return new EmailSettingsEntity(
            id: EmailSettingsEntity::ID,
            provider: $settings->provider?->value,
            host: $settings->host,
            port: $settings->port,
            username: $settings->username,
            password: $settings->password,
            fromAddress: $settings->fromAddress,
            fromName: $settings->fromName,
            notificationRecipients: $settings->notificationRecipients,
            updatedAt: $updatedAt,
        );
    }

    public function updateEntity(EmailSettings $settings, EmailSettingsEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update(
            $settings->provider?->value,
            $settings->host,
            $settings->port,
            $settings->username,
            $settings->password,
            $settings->fromAddress,
            $settings->fromName,
            $settings->notificationRecipients,
            $updatedAt,
        );
    }
}
