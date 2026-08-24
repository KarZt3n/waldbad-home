<?php

namespace App\Logic\Event\Activity\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Activity\Dto\EventActivityResponse;
use App\Logic\Event\Activity\Dto\UpdateEventActivityRequest;
use App\Logic\Event\Activity\Manager\EventActivityManagerInterface;

readonly class UpdateEventActivityUseCase
{
    public function __construct(
        private EventActivityManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdateEventActivityRequest $request): EventActivityResponse
    {
        $activity = $this->manager->get($request->id)->update(
            $request->name,
            $request->description,
            $request->active,
            $this->clock->now(),
        );

        return EventActivityResponse::fromActivity($this->manager->save($activity));
    }
}
