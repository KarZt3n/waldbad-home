<?php

namespace App\Data\Content\Extension\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'page_extension_texts')]
class PageExtensionTextsEntity
{
    /**
     * @param array<string, string> $texts
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'extension_key', type: Types::STRING, length: 60)]
        private string $extensionKey,
        #[ORM\Column(type: Types::JSON)]
        private array $texts,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getExtensionKey(): string
    {
        return $this->extensionKey;
    }

    /**
     * @return array<string, string>
     */
    public function getTexts(): array
    {
        return $this->texts;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param array<string, string> $texts
     */
    public function update(array $texts, \DateTimeImmutable $updatedAt): void
    {
        $this->texts = $texts;
        $this->updatedAt = $updatedAt;
    }
}
