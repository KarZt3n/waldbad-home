<?php

namespace App\Logic\Event\Template\Dto;

use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Template\Model\EventTemplateActivity;
use App\Logic\Event\Template\Model\EventTemplateCallToAction;

readonly class CreateEventTemplateRequest
{
    /**
     * @param list<EventTemplateActivity> $activities
     * @param list<EventTemplateCallToAction> $callToActions
     */
    public function __construct(
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
    ) {
    }
}
