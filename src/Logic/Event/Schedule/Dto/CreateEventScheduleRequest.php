<?php

namespace App\Logic\Event\Schedule\Dto;

use App\Logic\Event\Schedule\Model\EventScheduleActivity;
use App\Logic\Event\Schedule\Model\EventScheduleCallToAction;
use App\Logic\Event\Schedule\Model\EventScheduleKind;

readonly class CreateEventScheduleRequest
{
    /**
     * @param list<EventScheduleActivity> $activities
     * @param list<EventScheduleCallToAction> $callToActions
     */
    public function __construct(
        public EventScheduleKind $kind,
        public string $title,
        public string $date,
        public string $time,
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
        public bool $visible,
        public array $activities,
        public array $callToActions,
    ) {
    }
}
