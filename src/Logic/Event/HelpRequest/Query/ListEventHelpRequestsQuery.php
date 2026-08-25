<?php

namespace App\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

readonly class ListEventHelpRequestsQuery
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private VolunteerEventProviderInterface $eventProvider,
    ) {
    }

    /**
     * @return list<EventHelpRequestResponse>
     */
    public function execute(): array
    {
        $responses = [];
        $currentEvents = [];
        foreach ($this->manager->all() as $request) {
            if (!array_key_exists($request->eventIdentifier, $currentEvents)) {
                $currentEvents[$request->eventIdentifier] = $this->eventProvider->findCurrent($request->eventIdentifier);
            }
            $responses[] = EventHelpRequestResponse::fromRequest(
                $request,
                $currentEvents[$request->eventIdentifier],
            );
        }

        return $responses;
    }
}
