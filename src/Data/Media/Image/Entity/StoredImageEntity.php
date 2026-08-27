<?php

namespace App\Data\Media\Image\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'media_image')]
#[ORM\UniqueConstraint(name: 'uniq_media_image_url', columns: ['url'])]
#[ORM\Index(name: 'idx_media_image_created', columns: ['created_at'])]
class StoredImageEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $url,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $originalName,
        #[ORM\Column(type: Types::STRING, length: 80)]
        private string $mimeType,
        #[ORM\Column(type: Types::INTEGER)]
        private int $size,
        #[ORM\Column(type: Types::INTEGER)]
        private int $width,
        #[ORM\Column(type: Types::INTEGER)]
        private int $height,
        #[ORM\Column(type: Types::STRING, length: 300, nullable: true)]
        private ?string $source,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getUrl(): string { return $this->url; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }
    public function getSource(): ?string { return $this->source; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function updateFileMetadata(
        string $originalName,
        string $mimeType,
        int $size,
        int $width,
        int $height,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->width = $width;
        $this->height = $height;
        $this->updatedAt = $updatedAt;
    }

    public function updateSource(?string $source, \DateTimeImmutable $updatedAt): void
    {
        $this->source = $source;
        $this->updatedAt = $updatedAt;
    }
}
