<?php

namespace App\Logic\Event\Schedule\UseCase;

use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;

readonly class DeleteEventScheduleUseCase
{
    public function __construct(private EventScheduleManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $this->manager->get($id);
        $this->manager->delete($id);
    }
}
