<?php

namespace App\Data\Event\Schedule\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_schedule_call_to_action')]
#[ORM\Index(name: 'idx_event_schedule_cta_schedule_position', columns: ['schedule_id', 'position'])]
class EventScheduleCallToActionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: EventScheduleEntity::class, inversedBy: 'callToActions')]
        #[ORM\JoinColumn(name: 'schedule_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private EventScheduleEntity $schedule,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
        #[ORM\Column(type: Types::STRING, length: 80)]
        private string $label,
        #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
        private ?string $url,
        #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
        private ?string $pageId,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getPosition(): int { return $this->position; }
    public function getLabel(): string { return $this->label; }
    public function getUrl(): ?string { return $this->url; }
    public function getPageId(): ?string { return $this->pageId; }
}
