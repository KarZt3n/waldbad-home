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
    ) {
    }
}
