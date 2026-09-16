<?php

namespace App\Data\Event\Template\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_template_call_to_action')]
#[ORM\Index(name: 'idx_event_template_cta_template_position', columns: ['template_id', 'position'])]
class EventTemplateCallToActionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: EventTemplateEntity::class, inversedBy: 'callToActions')]
        #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private EventTemplateEntity $template,
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
