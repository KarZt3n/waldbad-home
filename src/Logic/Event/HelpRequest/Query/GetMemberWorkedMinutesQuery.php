<?php

namespace App\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;

/**
 * Wiederverwendbare Schnittstelle zum Abrufen geleisteter Helferstunden (Status „Teilgenommen") aus
 * der „Ich möchte Helfen!"-Funktion für einen beliebigen Zeitraum [$from, $to) — Grundlage sowohl
 * für die Arbeitseinsatz-Gutschrift in „Meine Mitgliedschaft" (siehe `WorkAssignmentCreditCalculator`)
 * als auch für den künftigen Arbeitsstunden-Export für die Schatzmeisterei.
 */
readonly class GetMemberWorkedMinutesQuery
{
    public function __construct(private EventHelpRequestManagerInterface $manager)
    {
    }

    /**
     * @param list<string> $memberIds
     * @return array<string, int> Mitglieds-ID => Summe der geleisteten Minuten im Zeitraum
     */
    public function minutesByMember(array $memberIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $minutes = array_fill_keys($memberIds, 0);
        foreach ($this->manager->findParticipatedForMembersInPeriod($memberIds, $from, $to) as $request) {
            if ($request->memberId !== null) {
                $minutes[$request->memberId] = ($minutes[$request->memberId] ?? 0) + ($request->participationMinutes ?? 0);
            }
        }

        return $minutes;
    }

    /**
     * @param list<string> $memberIds
     */
    public function totalMinutes(array $memberIds, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return array_sum($this->minutesByMember($memberIds, $from, $to));
    }
}
