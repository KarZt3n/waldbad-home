<?php

namespace App\Data\Event\HelpRequest\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_help_request_activity')]
#[ORM\UniqueConstraint(name: 'uniq_event_help_request_activity', columns: ['request_id', 'activity_id'])]
class EventHelpRequestActivityEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: EventHelpRequestEntity::class, inversedBy: 'selectedActivities')]
        #[ORM\JoinColumn(name: 'request_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private EventHelpRequestEntity $request,
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $activityId,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $activityName,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getActivityId(): string { return $this->activityId; }
    public function getActivityName(): string { return $this->activityName; }
}
