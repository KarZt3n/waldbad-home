<?php

namespace App\Data\Settings\Pin\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einzeilige Singleton-Tabelle für die PIN-Einstellungen (siehe `PinSettings`). Die feste Id
 * `self::ID` verhindert, dass versehentlich mehrere Zeilen entstehen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pin_settings')]
class PinSettingsEntity
{
    public const string ID = 'default';

    /**
     * @param list<string> $protectedActions
     * @param array<string, string> $actionPinHashes
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $id,
        #[ORM\Column(name: 'pin_hash', type: Types::STRING, length: 255, nullable: true)]
        private ?string $globalPinHash,
        #[ORM\Column(type: Types::JSON)]
        private array $protectedActions,
        #[ORM\Column(type: Types::JSON, nullable: true)]
        private ?array $actionPinHashes,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGlobalPinHash(): ?string
    {
        return $this->globalPinHash;
    }

    /**
     * @return list<string>
     */
    public function getProtectedActions(): array
    {
        return $this->protectedActions;
    }

    /**
     * @return array<string, string>
     */
    public function getActionPinHashes(): array
    {
        return $this->actionPinHashes ?? [];
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param list<string> $protectedActions
     * @param array<string, string> $actionPinHashes
     */
    public function update(?string $globalPinHash, array $protectedActions, array $actionPinHashes, \DateTimeImmutable $updatedAt): void
    {
        $this->globalPinHash = $globalPinHash;
        $this->protectedActions = $protectedActions;
        $this->actionPinHashes = $actionPinHashes;
        $this->updatedAt = $updatedAt;
    }
}
