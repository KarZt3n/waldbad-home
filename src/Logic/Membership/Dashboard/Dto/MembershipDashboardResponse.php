<?php

namespace App\Logic\Membership\Dashboard\Dto;

readonly class MembershipDashboardResponse
{
    /**
     * @param list<ContributionRateCount> $contributionRateCounts
     */
    public function __construct(
        public int $totalMembers,
        public int $pendingApplications,
        /** Summe aus Mitgliedsbeitrag + Arbeitseinsatz-Zuschlag über alle Mitglieder, in Cent. */
        public int $totalContributionCents,
        /**
         * Mitglieder, deren Austrittsdatum auf den 31.12. des laufenden Jahres fällt — laut
         * Beitrags- und Kassenordnung ist eine Kündigung nur fristgemäß zum Jahresende möglich,
         * daher liegt ein gesetztes Austrittsdatum praktisch immer auf diesen Tag.
         */
        public int $leavingAtYearEnd,
        /** Dieselbe Auswertung wie `$leavingAtYearEnd`, aber für den 31.12. des Vorjahres. */
        public int $leftLastYearEnd,
        /** Alle Mitglieder mit Zahlerart „Selbstzahler“ — bewusst inklusive ausgetretener (Nutzer-Vorgabe). */
        public int $payers,
        /**
         * Aktive Selbstzahler, unter deren Hauptnummer mehr als eine aktive Person geführt wird — jeder
         * zählt als eigene Familie, auch wenn mehrere Selbstzahler dieselbe Hauptnummer haben. Diese und
         * die folgenden Kennzahlen zählen nur aktive Mitglieder (siehe `Member::isActive()`).
         */
        public int $families,
        /**
         * Aktive Selbstzahler, die als einzige aktive Person unter ihrer Hauptnummer geführt werden —
         * zusammen mit `$families` also alle aktiven Selbstzahler.
         */
        public int $individualMemberships,
        /**
         * Aktive Mitglieder, die heute tatsächlich mindestens 21 sind (siehe `Member::ageAt()`) — bewusst
         * nicht das Beitragsalter zum Stichtag 31.12., nach dem sich die Beitragssätze richten.
         */
        public int $adults,
        /** Aktive Mitglieder, die heute tatsächlich jünger als 21 sind. */
        public int $minors,
        /** Anzahl aktiver Mitglieder je Beitragssatz. */
        public array $contributionRateCounts,
    ) {
    }
}
