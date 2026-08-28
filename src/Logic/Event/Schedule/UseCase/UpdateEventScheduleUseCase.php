<?php

namespace App\Logic\Event\Schedule\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Schedule\Dto\EventScheduleResponse;
use App\Logic\Event\Schedule\Dto\UpdateEventScheduleRequest;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;

readonly class UpdateEventScheduleUseCase
{
    public function __construct(
        private EventScheduleManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdateEventScheduleRequest $request): EventScheduleResponse
    {
        $schedule = $this->manager->get($request->id)->revise(
            title: $request->title,
            date: $request->date,
            time: $request->time,
            content: $request->content,
            mediaUrl: $request->mediaUrl,
            mediaAlt: $request->mediaAlt,
            mediaSource: $request->mediaSource,
            layout: $request->layout,
            imageWidthPercent: $request->imageWidthPercent,
            verticalAlignment: $request->verticalAlignment,
            textAlignment: $request->textAlignment,
            imageFit: $request->imageFit,
            helpEnabled: $request->helpEnabled,
            helpButtonLabel: $request->helpButtonLabel,
            visible: $request->visible,
            activities: $request->activities,
            callToActions: $request->callToActions,
            updatedAt: $this->clock->now(),
        );

        return EventScheduleResponse::fromSchedule($this->manager->save($schedule));
    }
}
