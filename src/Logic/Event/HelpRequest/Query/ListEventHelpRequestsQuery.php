<?php

namespace App\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\MemberProviderInterface;

readonly class ListEventHelpRequestsQuery
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private VolunteerEventProviderInterface $eventProvider,
        private MemberProviderInterface $memberProvider,
    ) {
    }

    /**
     * @return list<EventHelpRequestResponse>
     */
    public function execute(): array
    {
        $responses = [];
        $currentEvents = [];
        /** @var array<string, ?Member> $members */
        $members = [];
        foreach ($this->manager->all() as $request) {
            if (!array_key_exists($request->eventIdentifier, $currentEvents)) {
                $currentEvents[$request->eventIdentifier] = $this->eventProvider->findCurrent($request->eventIdentifier);
            }
            if ($request->memberId !== null && !array_key_exists($request->memberId, $members)) {
                $members[$request->memberId] = $this->memberProvider->find($request->memberId);
            }
            $member = $request->memberId === null ? null : $members[$request->memberId];
            $responses[] = EventHelpRequestResponse::fromRequest(
                $request,
                $currentEvents[$request->eventIdentifier],
                $member?->memberNumber,
                $member?->firstName,
                $member?->lastName,
            );
        }

        return $responses;
    }
}
