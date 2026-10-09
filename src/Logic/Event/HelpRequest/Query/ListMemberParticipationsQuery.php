<?php

namespace App\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;

/**
 * Alle Helferanmeldungen mit Status „Teilgenommen“ der übergebenen Mitglieder, über alle Jahre —
 * Grundlage für die Arbeitseinsatz-Übersicht in der Mitgliederverwaltung (siehe
 * `MemberWorkAssignmentProviderInterface`). Neueste Veranstaltung zuerst.
 */
readonly class ListMemberParticipationsQuery
{
    public function __construct(private EventHelpRequestManagerInterface $manager)
    {
    }

    /**
     * @param list<string> $memberIds
     * @return list<EventHelpRequestResponse>
     */
    public function execute(array $memberIds): array
    {
        $participations = array_values(array_filter(
            $this->manager->all(),
            static fn (EventHelpRequest $request): bool => $request->status === EventHelpRequestStatus::Participated
                && $request->memberId !== null
                && in_array($request->memberId, $memberIds, true),
        ));
        usort($participations, static fn (EventHelpRequest $left, EventHelpRequest $right): int => [$right->eventDate, $right->eventTime] <=> [$left->eventDate, $left->eventTime]);

        return array_map(EventHelpRequestResponse::fromRequest(...), $participations);
    }
}
