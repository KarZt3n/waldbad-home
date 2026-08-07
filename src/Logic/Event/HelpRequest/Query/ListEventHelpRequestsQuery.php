<?php

namespace App\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;

readonly class ListEventHelpRequestsQuery
{
    public function __construct(private EventHelpRequestManagerInterface $manager)
    {
    }

    /**
     * @return list<EventHelpRequestResponse>
     */
    public function execute(): array
    {
        return array_map(EventHelpRequestResponse::fromRequest(...), $this->manager->all());
    }
}
