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
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        private ?string $provider,
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        private ?string $host,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $port,
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        private ?string $username,
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        private ?string $password,
        #[ORM\Column(name: 'from_address', type: Types::STRING, length: 255, nullable: true)]
        private ?string $fromAddress,
        #[ORM\Column(name: 'from_name', type: Types::STRING, length: 180, nullable: true)]
        private ?string $fromName,
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

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getFromAddress(): ?string
    {
        return $this->fromAddress;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
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
    public function update(
        ?string $provider,
        ?string $host,
        ?int $port,
        ?string $username,
        ?string $password,
        ?string $fromAddress,
        ?string $fromName,
        array $notificationRecipients,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->provider = $provider;
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
        $this->notificationRecipients = $notificationRecipients;
        $this->updatedAt = $updatedAt;
    }
}
