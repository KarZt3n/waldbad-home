<?php

namespace App\Logic\Event\Schedule\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\Schedule\Dto\CreateEventScheduleRequest;
use App\Logic\Event\Schedule\Dto\EventScheduleResponse;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;

readonly class CreateEventScheduleUseCase
{
    public function __construct(
        private EventScheduleManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreateEventScheduleRequest $request): EventScheduleResponse
    {
        $now = $this->clock->now();

        $schedule = new EventSchedule(
            id: $this->identifierGenerator->generate(),
            kind: $request->kind,
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
            createdAt: $now,
            updatedAt: $now,
        );

        return EventScheduleResponse::fromSchedule($this->manager->save($schedule));
    }
}
