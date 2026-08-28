<?php

namespace App\Logic\Event\Schedule\Dto;

use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleActivity;
use App\Logic\Event\Schedule\Model\EventScheduleCallToAction;
use App\Logic\Event\Schedule\Model\EventScheduleKind;

readonly class EventScheduleResponse
{
    /**
     * @param list<EventScheduleActivity> $activities
     * @param list<EventScheduleCallToAction> $callToActions
     */
    public function __construct(
        public string $id,
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
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromSchedule(EventSchedule $schedule): self
    {
        return new self(
            id: $schedule->id,
            kind: $schedule->kind,
            title: $schedule->title,
            date: $schedule->date,
            time: $schedule->time,
            content: $schedule->content,
            mediaUrl: $schedule->mediaUrl,
            mediaAlt: $schedule->mediaAlt,
            mediaSource: $schedule->mediaSource,
            layout: $schedule->layout,
            imageWidthPercent: $schedule->imageWidthPercent,
            verticalAlignment: $schedule->verticalAlignment,
            textAlignment: $schedule->textAlignment,
            imageFit: $schedule->imageFit,
            helpEnabled: $schedule->helpEnabled,
            helpButtonLabel: $schedule->helpButtonLabel,
            visible: $schedule->visible,
            activities: $schedule->activities,
            callToActions: $schedule->callToActions,
            createdAt: $schedule->createdAt,
            updatedAt: $schedule->updatedAt,
        );
    }
}
