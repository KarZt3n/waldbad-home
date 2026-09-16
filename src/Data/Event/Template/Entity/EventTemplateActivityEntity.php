<?php

namespace App\Data\Event\Template\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_template_activity')]
#[ORM\Index(name: 'idx_event_template_activity_template_position', columns: ['template_id', 'position'])]
#[ORM\Index(name: 'idx_event_template_activity_activity', columns: ['activity_id'])]
class EventTemplateActivityEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: EventTemplateEntity::class, inversedBy: 'activities')]
        #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private EventTemplateEntity $template,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $activityId,
        #[ORM\Column(type: Types::INTEGER)]
        private int $requiredHelpers,
        #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
        private ?string $time,
        #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
        private ?string $meetTime,
        #[ORM\Column(type: Types::STRING, length: 160, nullable: true)]
        private ?string $meetPlace,
        #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
        private ?string $remark,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getPosition(): int { return $this->position; }
    public function getActivityId(): string { return $this->activityId; }
    public function getRequiredHelpers(): int { return $this->requiredHelpers; }
    public function getTime(): ?string { return $this->time; }
    public function getMeetTime(): ?string { return $this->meetTime; }
    public function getMeetPlace(): ?string { return $this->meetPlace; }
    public function getRemark(): ?string { return $this->remark; }
}
