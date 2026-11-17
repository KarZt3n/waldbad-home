<?php

namespace App\Logic\Event\HelpRequest;

use App\Logic\Event\HelpRequest\Model\VolunteerEvent;

interface VolunteerEventProviderInterface
{
    public function findPublished(string $eventIdentifier): ?VolunteerEvent;

    public function findCurrent(string $eventIdentifier): ?VolunteerEvent;

    /**
     * Alle Veranstaltungen mit aktivierter Helferanmeldung, unabhängig davon, ob sie veröffentlicht
     * sind — die Verwaltung soll Helfer auch vorab oder nachträglich eintragen können.
     *
     * @return list<VolunteerEvent>
     */
    public function findAllWithHelpEnabled(): array;
}
