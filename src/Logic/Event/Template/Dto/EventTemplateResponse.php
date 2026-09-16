<?php

namespace App\Logic\Event\Template\Dto;

use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Template\Model\EventTemplate;

readonly class EventTemplateResponse
{
    /**
     * @param list<\App\Logic\Event\Template\Model\EventTemplateActivity> $activities
     * @param list<\App\Logic\Event\Template\Model\EventTemplateCallToAction> $callToActions
     */
    public function __construct(
        public string $id,
        public EventScheduleKind $kind,
        public string $title,
        public string $content,
        public ?string $mediaUrl,
        public ?string $mediaAlt,
        public ?string $mediaSource,
        public ?string $layout,
        public ?int $imageWidthPercent,
        public ?string $verticalAlignment,
        public ?string $textAlignment,
        public ?string $imageFit,
        public bool $helpEnabled,
        public ?string $helpButtonLabel,
        public array $activities,
        public array $callToActions,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromTemplate(EventTemplate $template): self
    {
        return new self(
            id: $template->id,
            kind: $template->kind,
            title: $template->title,
            content: $template->content,
            mediaUrl: $template->mediaUrl,
            mediaAlt: $template->mediaAlt,
            mediaSource: $template->mediaSource,
            layout: $template->layout,
            imageWidthPercent: $template->imageWidthPercent,
            verticalAlignment: $template->verticalAlignment,
            textAlignment: $template->textAlignment,
            imageFit: $template->imageFit,
            helpEnabled: $template->helpEnabled,
            helpButtonLabel: $template->helpButtonLabel,
            activities: $template->activities,
            callToActions: $template->callToActions,
            createdAt: $template->createdAt,
            updatedAt: $template->updatedAt,
        );
    }
}
