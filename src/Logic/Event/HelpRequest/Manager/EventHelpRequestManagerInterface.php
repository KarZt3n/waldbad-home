<?php

namespace App\Logic\Event\HelpRequest\Manager;

use App\Logic\Event\HelpRequest\Model\EventHelpRequest;

interface EventHelpRequestManagerInterface
{
    public function get(string $id): EventHelpRequest;

    /**
     * @return list<EventHelpRequest>
     */
    public function all(): array;

    public function save(EventHelpRequest $request): EventHelpRequest;
}
