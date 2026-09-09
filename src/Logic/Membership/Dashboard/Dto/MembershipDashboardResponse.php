<?php

namespace App\Logic\Membership\Dashboard\Dto;

readonly class MembershipDashboardResponse
{
    public function __construct(
        public int $totalMembers,
        public int $activeMembers,
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
    ) {
    }
}
