<?php

namespace App\Data\Event\Template\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_template')]
#[ORM\Index(name: 'idx_event_template_kind_title', columns: ['kind', 'title'])]
class EventTemplateEntity
{
    /** @var Collection<int, EventTemplateActivityEntity> */
    #[ORM\OneToMany(targetEntity: EventTemplateActivityEntity::class, mappedBy: 'template', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $activities;

    /** @var Collection<int, EventTemplateCallToActionEntity> */
    #[ORM\OneToMany(targetEntity: EventTemplateCallToActionEntity::class, mappedBy: 'template', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $callToActions;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $kind,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $title,
        #[ORM\Column(type: Types::TEXT)]
        private string $content,
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        private ?string $mediaUrl,
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        private ?string $mediaAlt,
        #[ORM\Column(type: Types::STRING, length: 300, nullable: true)]
        private ?string $mediaSource,
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        private ?string $layout,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $imageWidthPercent,
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        private ?string $verticalAlignment,
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        private ?string $textAlignment,
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        private ?string $imageFit,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $helpEnabled,
        #[ORM\Column(type: Types::STRING, length: 80, nullable: true)]
        private ?string $helpButtonLabel,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->activities = new ArrayCollection();
        $this->callToActions = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getKind(): string { return $this->kind; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getMediaUrl(): ?string { return $this->mediaUrl; }
    public function getMediaAlt(): ?string { return $this->mediaAlt; }
    public function getMediaSource(): ?string { return $this->mediaSource; }
    public function getLayout(): ?string { return $this->layout; }
    public function getImageWidthPercent(): ?int { return $this->imageWidthPercent; }
    public function getVerticalAlignment(): ?string { return $this->verticalAlignment; }
    public function getTextAlignment(): ?string { return $this->textAlignment; }
    public function getImageFit(): ?string { return $this->imageFit; }
    public function isHelpEnabled(): bool { return $this->helpEnabled; }
    public function getHelpButtonLabel(): ?string { return $this->helpButtonLabel; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return list<EventTemplateActivityEntity> */
    public function getActivities(): array { return array_values($this->activities->toArray()); }

    /** @param list<EventTemplateActivityEntity> $activities */
    public function replaceActivities(array $activities): void
    {
        $this->activities->clear();
        foreach ($activities as $activity) {
            $this->activities->add($activity);
        }
    }

    /** @return list<EventTemplateCallToActionEntity> */
    public function getCallToActions(): array { return array_values($this->callToActions->toArray()); }

    /** @param list<EventTemplateCallToActionEntity> $callToActions */
    public function replaceCallToActions(array $callToActions): void
    {
        $this->callToActions->clear();
        foreach ($callToActions as $callToAction) {
            $this->callToActions->add($callToAction);
        }
    }

    public function update(
        string $title,
        string $content,
        ?string $mediaUrl,
        ?string $mediaAlt,
        ?string $mediaSource,
        ?string $layout,
        ?int $imageWidthPercent,
        ?string $verticalAlignment,
        ?string $textAlignment,
        ?string $imageFit,
        bool $helpEnabled,
        ?string $helpButtonLabel,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->title = $title;
        $this->content = $content;
        $this->mediaUrl = $mediaUrl;
        $this->mediaAlt = $mediaAlt;
        $this->mediaSource = $mediaSource;
        $this->layout = $layout;
        $this->imageWidthPercent = $imageWidthPercent;
        $this->verticalAlignment = $verticalAlignment;
        $this->textAlignment = $textAlignment;
        $this->imageFit = $imageFit;
        $this->helpEnabled = $helpEnabled;
        $this->helpButtonLabel = $helpButtonLabel;
        $this->updatedAt = $updatedAt;
    }
}
