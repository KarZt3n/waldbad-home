<?php

namespace App\Data\Settings\Email\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einzeilige Singleton-Tabelle für die E-Mail-Einstellungen (siehe `EmailSettings`). Die feste Id
 * `self::ID` verhindert, dass versehentlich mehrere Zeilen entstehen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'email_settings')]
class EmailSettingsEntity
{
    public const string ID = 'default';

    /**
     * @param array<string, list<string>> $notificationRecipients
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $id,
        #[ORM\Column(type: Types::JSON, nullable: true)]
        private ?array $notificationRecipients,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getNotificationRecipients(): array
    {
        return $this->notificationRecipients ?? [];
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param array<string, list<string>> $notificationRecipients
     */
    public function update(array $notificationRecipients, \DateTimeImmutable $updatedAt): void
    {
        $this->notificationRecipients = $notificationRecipients;
        $this->updatedAt = $updatedAt;
    }
}
