<?php

namespace App\Data\Content\Footer\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einzeilige Singleton-Tabelle für die Seiten im Footer (siehe `FooterNavigation`). Die feste Id
 * `self::ID` verhindert, dass versehentlich mehrere Zeilen entstehen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'footer_navigation_settings')]
class FooterNavigationEntity
{
    public const string ID = 'default';

    /**
     * @param list<string> $pageIds
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $id,
        #[ORM\Column(type: Types::JSON)]
        private array $pageIds,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return list<string>
     */
    public function getPageIds(): array
    {
        return $this->pageIds;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param list<string> $pageIds
     */
    public function update(array $pageIds, \DateTimeImmutable $updatedAt): void
    {
        $this->pageIds = $pageIds;
        $this->updatedAt = $updatedAt;
    }
}
