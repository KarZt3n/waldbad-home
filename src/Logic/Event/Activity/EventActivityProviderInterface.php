<?php

namespace App\Logic\Event\Activity;

use App\Logic\Event\Activity\Model\EventActivity;

interface EventActivityProviderInterface
{
    public function find(string $id): ?EventActivity;

    /** @return list<EventActivity> */
    public function findAll(): array;
}
