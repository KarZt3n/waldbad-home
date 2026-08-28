<?php

namespace App\Data\Event\Schedule\Provider;

use App\Data\Event\HelpRequest\Provider\PublishedContentVolunteerEventProvider;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

/**
 * Default `VolunteerEventProviderInterface` binding: looks up the standalone „Veranstaltung“
 * module first, then falls back to legacy `event`/`event_reference` page content blocks. This
 * keeps helper sign-ups working unchanged for events created before the module existed.
 */
readonly class ChainVolunteerEventProvider implements VolunteerEventProviderInterface
{
    public function __construct(
        private EventScheduleVolunteerEventProvider $scheduleProvider,
        private PublishedContentVolunteerEventProvider $legacyPageProvider,
    ) {
    }

    public function findPublished(string $eventIdentifier): ?VolunteerEvent
    {
        return $this->scheduleProvider->findPublished($eventIdentifier)
            ?? $this->legacyPageProvider->findPublished($eventIdentifier);
    }

    public function findCurrent(string $eventIdentifier): ?VolunteerEvent
    {
        return $this->scheduleProvider->findCurrent($eventIdentifier)
            ?? $this->legacyPageProvider->findCurrent($eventIdentifier);
    }
}
