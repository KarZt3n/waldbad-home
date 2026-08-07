<?php

namespace App\Data\Event\HelpRequest\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_help_interval')]
#[ORM\Index(name: 'idx_event_help_interval_request_position', columns: ['request_id', 'position'])]
class EventHelpIntervalEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: EventHelpRequestEntity::class, inversedBy: 'participationIntervals')]
        #[ORM\JoinColumn(name: 'request_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private EventHelpRequestEntity $request,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $fromTime,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $toTime,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getPosition(): int { return $this->position; }
    public function getFromTime(): string { return $this->fromTime; }
    public function getToTime(): string { return $this->toTime; }
}
