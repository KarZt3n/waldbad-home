<?php

namespace App\Logic\Event\HelpRequest;

use App\Logic\Event\HelpRequest\Model\VolunteerEvent;

interface VolunteerEventProviderInterface
{
    public function findPublished(string $eventIdentifier): ?VolunteerEvent;

    public function findCurrent(string $eventIdentifier): ?VolunteerEvent;

    /**
     * Alle Veranstaltungen, unabhängig von Veröffentlichung und öffentlicher Helferanmeldung — die
     * Verwaltung soll zu jeder Veranstaltung Helfer eintragen können, auch vorab oder nachträglich.
     *
     * @return list<VolunteerEvent>
     */
    public function findAll(): array;
}
