<?php

namespace App\Data\Membership\WorkAssignment\Adapter;

use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;
use App\Logic\Event\HelpRequest\Query\ListMemberParticipationsQuery;
use App\Logic\Membership\WorkAssignment\MemberWorkAssignmentProviderInterface;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentInterval;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;

/**
 * Bindet die Helferanmeldung als externes System an die Mitgliederverwaltung an (Adapter-Prinzip,
 * `architektur.md` Abschnitt 2.4).
 */
readonly class EventHelpRequestWorkAssignmentProvider implements MemberWorkAssignmentProviderInterface
{
    public function __construct(private ListMemberParticipationsQuery $query)
    {
    }

    public function findForMembers(array $memberIds): array
    {
        $participations = [];
        foreach ($this->query->execute($memberIds) as $request) {
            if ($request->memberId === null) {
                continue;
            }
            $participations[] = $this->toParticipation($request, $request->memberId);
        }

        return $participations;
    }

    private function toParticipation(EventHelpRequestResponse $request, string $memberId): WorkAssignmentParticipation
    {
        $intervals = $request->participationIntervals;
        usort($intervals, static fn (ParticipationInterval $left, ParticipationInterval $right): int => $left->startsAtMinute() <=> $right->startsAtMinute());

        return new WorkAssignmentParticipation(
            memberId: $memberId,
            eventTitle: $request->eventTitle,
            eventDate: $request->eventDate,
            eventTime: $request->eventTime,
            minutes: $request->participationMinutes ?? 0,
            intervals: array_map(
                static fn (ParticipationInterval $interval): WorkAssignmentInterval => new WorkAssignmentInterval($interval->fromTime, $interval->toTime),
                $intervals,
            ),
        );
    }
}
