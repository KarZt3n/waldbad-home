<?php

namespace App\Logic\Membership\Dashboard\Query;

use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Dashboard\Dto\MembershipDashboardResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;

/**
 * Bewusst schlanker Platzhalter für das Mitgliederverwaltungs-Dashboard — liefert nur die
 * wichtigsten Kennzahlen. Wird schrittweise um weitere Auswertungen ergänzt.
 */
readonly class GetMembershipDashboardQuery
{
    public function __construct(
        private MemberManagerInterface $members,
        private MembershipApplicationManagerInterface $applications,
    ) {
    }

    public function execute(): MembershipDashboardResponse
    {
        $members = $this->members->search(null);
        // „Offen“ heißt: über den Antrag wurde noch nicht entschieden (weder als Mitglied
        // angelegt noch abgelehnt).
        $pendingApplications = array_filter(
            $this->applications->list(),
            static fn (MembershipApplication $application): bool => $application->releasedAt === null && $application->rejectedAt === null,
        );

        $totalContributionCents = (int) array_sum(array_map(
            static fn (Member $member): int => ($member->contributionAmountCents ?? 0) + ($member->workAssignmentSurchargeCents ?? 0),
            $members,
        ));

        return new MembershipDashboardResponse(
            totalMembers: count($members),
            activeMembers: count(array_filter($members, static fn (Member $member): bool => $member->active)),
            pendingApplications: count($pendingApplications),
            totalContributionCents: $totalContributionCents,
        );
    }
}
