<?php

namespace App\Logic\Event\Activity\Query;

use App\Logic\Event\Activity\Dto\EventActivityResponse;
use App\Logic\Event\Activity\Manager\EventActivityManagerInterface;
use App\Logic\Event\Activity\Model\EventActivity;

readonly class ListEventActivitiesQuery
{
    public function __construct(private EventActivityManagerInterface $manager)
    {
    }

    /** @return list<EventActivityResponse> */
    public function execute(bool $activeOnly = false): array
    {
        return array_values(array_map(
            EventActivityResponse::fromActivity(...),
            array_filter($this->manager->all(), static fn (EventActivity $activity): bool => !$activeOnly || $activity->active),
        ));
    }
}
