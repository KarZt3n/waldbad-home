<?php

namespace App\Data\Event\Activity\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_activity')]
#[ORM\Index(name: 'idx_event_activity_active_name', columns: ['active', 'name'])]
class EventActivityEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $name,
        #[ORM\Column(type: Types::TEXT)]
        private string $description,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $active,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $defaultRequiredHelpers,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function isActive(): bool { return $this->active; }
    public function getDefaultRequiredHelpers(): ?int { return $this->defaultRequiredHelpers; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function update(string $name, string $description, bool $active, ?int $defaultRequiredHelpers, \DateTimeImmutable $updatedAt): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->active = $active;
        $this->defaultRequiredHelpers = $defaultRequiredHelpers;
        $this->updatedAt = $updatedAt;
    }
}
