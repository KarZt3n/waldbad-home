<?php

namespace App\Data\Content\Branding\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einzeilige Singleton-Tabelle für das Website-Logo (siehe `SiteBranding`). Die feste Id `self::ID`
 * verhindert, dass versehentlich mehrere Zeilen entstehen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'site_branding')]
class SiteBrandingEntity
{
    public const string ID = 'default';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
        private ?string $logoUrl,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(?string $logoUrl, \DateTimeImmutable $updatedAt): void
    {
        $this->logoUrl = $logoUrl;
        $this->updatedAt = $updatedAt;
    }
}
