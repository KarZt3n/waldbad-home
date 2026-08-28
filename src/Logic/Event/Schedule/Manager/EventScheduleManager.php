<?php

namespace App\Logic\Event\Schedule\Manager;

use App\Logic\Event\Schedule\EventScheduleProcessorInterface;
use App\Logic\Event\Schedule\EventScheduleProviderInterface;
use App\Logic\Event\Schedule\Exception\EventScheduleNotFoundException;
use App\Logic\Event\Schedule\Model\EventSchedule;

readonly class EventScheduleManager implements EventScheduleManagerInterface
{
    public function __construct(
        private EventScheduleProviderInterface $provider,
        private EventScheduleProcessorInterface $processor,
    ) {
    }

    public function get(string $id): EventSchedule
    {
        return $this->provider->find($id) ?? throw new EventScheduleNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function save(EventSchedule $schedule): EventSchedule
    {
        return $this->processor->save($schedule);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
