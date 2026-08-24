<?php

namespace App\Logic\Event\Activity\Manager;

use App\Logic\Event\Activity\EventActivityProcessorInterface;
use App\Logic\Event\Activity\EventActivityProviderInterface;
use App\Logic\Event\Activity\Exception\EventActivityNotFoundException;
use App\Logic\Event\Activity\Model\EventActivity;

readonly class EventActivityManager implements EventActivityManagerInterface
{
    public function __construct(
        private EventActivityProviderInterface $provider,
        private EventActivityProcessorInterface $processor,
    ) {
    }

    public function get(string $id): EventActivity
    {
        return $this->provider->find($id) ?? throw new EventActivityNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function save(EventActivity $activity): EventActivity
    {
        return $this->processor->save($activity);
    }
}
