<?php

namespace App\Logic\Event\Activity\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\Activity\Dto\CreateEventActivityRequest;
use App\Logic\Event\Activity\Dto\EventActivityResponse;
use App\Logic\Event\Activity\Manager\EventActivityManagerInterface;
use App\Logic\Event\Activity\Model\EventActivity;

readonly class CreateEventActivityUseCase
{
    public function __construct(
        private EventActivityManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreateEventActivityRequest $request): EventActivityResponse
    {
        $now = $this->clock->now();

        return EventActivityResponse::fromActivity($this->manager->save(new EventActivity(
            id: $this->identifierGenerator->generate(),
            name: trim($request->name),
            description: trim($request->description),
            active: $request->active,
            defaultRequiredHelpers: $request->defaultRequiredHelpers,
            createdAt: $now,
            updatedAt: $now,
        )));
    }
}
