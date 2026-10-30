<?php

namespace App\Data\Content\SocialMedia\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einzeilige Singleton-Tabelle für die Social-Media-Leiste (siehe `SocialMediaSettings`). Die feste Id
 * `self::ID` verhindert, dass versehentlich mehrere Zeilen entstehen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'social_media_settings')]
class SocialMediaSettingsEntity
{
    public const string ID = 'default';

    /**
     * @param list<array{platform: string, url: string}> $links
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $id,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $visible,
        #[ORM\Column(type: Types::JSON)]
        private array $links,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @return list<array{platform: string, url: string}>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param list<array{platform: string, url: string}> $links
     */
    public function update(bool $visible, array $links, \DateTimeImmutable $updatedAt): void
    {
        $this->visible = $visible;
        $this->links = $links;
        $this->updatedAt = $updatedAt;
    }
}
