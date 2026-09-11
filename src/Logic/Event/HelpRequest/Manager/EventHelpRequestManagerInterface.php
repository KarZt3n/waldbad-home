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

    /**
     * Geleistete (Status „Teilgenommen") Helferanmeldungen der übergebenen Mitglieds-IDs, deren
     * Veranstaltungsdatum im Zeitraum [$from, $to) liegt ($to exklusiv) — die wiederverwendbare
     * Grundlage für die Arbeitseinsatz-Gutschrift in „Meine Mitgliedschaft" (siehe
     * `GetMemberWorkedMinutesQuery`) sowie für den künftigen Arbeitsstunden-Export für die
     * Schatzmeisterei.
     *
     * @param list<string> $memberIds
     * @return list<EventHelpRequest>
     */
    public function findParticipatedForMembersInPeriod(array $memberIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function save(EventHelpRequest $request): EventHelpRequest;

    /**
     * Wird nur für das Zusammenführen doppelter Anmeldungen genutzt (siehe
     * `EventHelpRequestDuplicateMerger`), nicht für eine eigene Lösch-Aktion in der Oberfläche.
     */
    public function delete(string $id): void;
}
