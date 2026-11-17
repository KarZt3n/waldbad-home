<?php

namespace App\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Dto\VolunteerEventOptionResponse;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

/**
 * Auswahlliste für „Neuen Helfer anmelden“ in der Helferverwaltung: alle Veranstaltungen mit
 * aktivierter Helferanmeldung, chronologisch sortiert.
 */
readonly class ListVolunteerEventOptionsQuery
{
    public function __construct(private VolunteerEventProviderInterface $eventProvider)
    {
    }

    /** @return list<VolunteerEventOptionResponse> */
    public function execute(): array
    {
        $events = $this->eventProvider->findAllWithHelpEnabled();
        usort($events, static fn (VolunteerEvent $left, VolunteerEvent $right): int => [$left->date, $left->time] <=> [$right->date, $right->time]);

        return array_map(VolunteerEventOptionResponse::fromEvent(...), $events);
    }
}
