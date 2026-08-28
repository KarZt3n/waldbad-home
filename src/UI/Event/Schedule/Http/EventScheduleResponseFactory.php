<?php

namespace App\UI\Event\Schedule\Http;

use App\Logic\Event\Schedule\Dto\EventScheduleResponse;
use App\Logic\Event\Schedule\Model\EventScheduleActivity;
use App\Logic\Event\Schedule\Model\EventScheduleCallToAction;

readonly class EventScheduleResponseFactory
{
    /** @return array<string, mixed> */
    public function schedule(EventScheduleResponse $schedule): array
    {
        return [
            'id' => $schedule->id,
            'kind' => $schedule->kind->value,
            'title' => $schedule->title,
            'date' => $schedule->date,
            'time' => $schedule->time,
            'content' => $schedule->content,
            'mediaUrl' => $schedule->mediaUrl,
            'mediaAlt' => $schedule->mediaAlt,
            'mediaSource' => $schedule->mediaSource,
            'layout' => $schedule->layout,
            'imageWidthPercent' => $schedule->imageWidthPercent,
            'verticalAlignment' => $schedule->verticalAlignment,
            'textAlignment' => $schedule->textAlignment,
            'imageFit' => $schedule->imageFit,
            'helpEnabled' => $schedule->helpEnabled,
            'helpButtonLabel' => $schedule->helpButtonLabel,
            'visible' => $schedule->visible,
            'activities' => array_map(
                static fn (EventScheduleActivity $activity): array => [
                    'activityId' => $activity->activityId,
                    'requiredHelpers' => $activity->requiredHelpers,
                    'time' => $activity->time,
                    'meetTime' => $activity->meetTime,
                    'meetPlace' => $activity->meetPlace,
                    'remark' => $activity->remark,
                ],
                $schedule->activities,
            ),
            'callToActions' => array_map(
                static fn (EventScheduleCallToAction $action): array => [
                    'label' => $action->label,
                    'url' => $action->url,
                    'pageId' => $action->pageId,
                ],
                $schedule->callToActions,
            ),
            'createdAt' => $schedule->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $schedule->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<EventScheduleResponse> $schedules
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function collection(array $schedules): array
    {
        return ['items' => array_map($this->schedule(...), $schedules), 'total' => count($schedules)];
    }
}
